<?php

namespace Revinate\RabbitMqBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;

/**
 * Class RevinateRabbitMqExtension
 * @package Revinate\RabbitMqBundle\DependencyInjection
 */
class RevinateRabbitMqExtension extends Extension
{
    /**
     * @var ContainerBuilder
     */
    private $container;
    /** @var array */
    private $config = array();

    /**
     * @param array $configs
     * @param ContainerBuilder $container
     */
    public function load(array $configs, ContainerBuilder $container) {
        $this->container = $container;
        $configuration = new Configuration();
        $this->config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $this->loadConnections();
        $this->loadExchanges();
        $this->loadQueues();
        $this->loadProducers();
        $this->loadconsumers();
    }

    /**
     * Load Connections
     */
    protected function loadConnections() {
        foreach ($this->config['connections'] as $key => $connection) {
            $classParam =
                $connection['lazy']
                    ? '%revinate_rabbit_mq.lazy.connection.class%'
                    : '%revinate_rabbit_mq.connection.class%';

            $definition = new Definition($classParam, array(
                $connection['host'],
                $connection['port'],
                $connection['user'],
                $connection['password'],
                $connection['vhost']
            ));

            $this->container->setDefinition(sprintf('revinate_rabbit_mq.connection.%s', $key), $definition);
        }
    }

    /**
     * Load Exchanges
     */
    protected function loadExchanges() {
        $servicesDefinition = $this->container->getDefinition('revinate.rabbit_mq.services');
        foreach ($this->config['exchanges'] as $key => $exchange) {
            $definition = new Definition('%revinate_rabbit_mq.exchange.class%', array(
                $key,
                $this->getConnection($exchange['connection']),
                $exchange['type'],
                $exchange['passive'],
                $exchange['durable'],
                $exchange['auto_delete'],
                $exchange['internal'],
                $exchange['nowait'],
                $exchange['arguments'],
                $exchange['ticket'],
                $exchange['managed'],
            ));
            $this->container->setDefinition(sprintf('revinate_rabbit_mq.exchange.%s', $key), $definition);
            $servicesDefinition->addMethodCall('addExchange', array(new Reference(sprintf('revinate_rabbit_mq.exchange.%s', $key))));
        }
    }

    /**
     * Load Queues
     */
    protected function loadQueues() {
        $servicesDefinition = $this->container->getDefinition('revinate.rabbit_mq.services');
        foreach ($this->config['queues'] as $key => $queue) {
            $definition = new Definition('%revinate_rabbit_mq.queue.class%', array(
                $key,
                $this->getExchange($queue['exchange']),
                $queue['passive'],
                $queue['durable'],
                $queue['exclusive'],
                $queue['auto_delete'],
                $queue['nowait'],
                $queue['arguments'],
                $queue['routing_keys'],
                $queue['ticket'],
                $queue['managed'],
            ));
            $this->container->setDefinition(sprintf('revinate_rabbit_mq.queue.%s', $key), $definition);
            $servicesDefinition->addMethodCall('addQueue', array(new Reference(sprintf('revinate_rabbit_mq.queue.%s', $key))));
        }
    }

    /**
     * Load Producers
     */
    protected function loadProducers() {
        foreach ($this->config['producers'] as $key => $producer) {
            $definition = new Definition('%revinate_rabbit_mq.producer.class%', array(
                $key,
                $this->getExchange($producer['exchange']),
            ));
            $this->container->setDefinition(sprintf('revinate_rabbit_mq.producer.%s', $key), $definition);
        }
    }

    /**
     * Load Consumers
     */
    protected function loadConsumers() {
        foreach ($this->config['consumers'] as $key => $consumer) {
            $definition = new Definition('%revinate_rabbit_mq.consumer.class%', array(
                $this->getContainer(),
                $key,
                $this->getQueue($consumer['queue']),
            ));
            $definition->addMethodCall('setCallback', array(array(new Reference($consumer['callback']), 'execute')));
            $definition->addMethodCall('setSetContainerCallback', array(array(new Reference($consumer['callback']), 'setContainer')));
            $definition->addMethodCall('setIdleTimeout', array($consumer['idle_timeout']));
            $definition->addMethodCall('setBatchSize', array($consumer['batch_size']));
            $definition->addMethodCall('setMessageClass', array($consumer['message_class']));
            $definition->addMethodCall('setBufferWait', array($consumer['buffer_wait']));
            if (isset($consumer['fairness_algorithm'])) {
                $definition->addMethodCall('setFairnessAlgorithm', array(new Reference($consumer['fairness_algorithm'])));
            }
            // Make prefetch-count to match batch-size if specified
            if (isset($consumer['batch_size'])) {
                $consumer['qos_options'] = isset($consumer['qos_options']) ? $consumer['qos_options'] :
                    array('prefetch_size' => 0, 'prefetch_count' => 0, 'global' => false);
                $consumer['qos_options'] = array_replace($consumer['qos_options'], array('prefetch_count' => $consumer['batch_size']));
            }
            if (array_key_exists('qos_options', $consumer)) {
                $definition->addMethodCall('setQosOptions', array(
                    $consumer['qos_options']['prefetch_size'],
                    $consumer['qos_options']['prefetch_count'],
                    $consumer['qos_options']['global']
                ));
            }
            $this->container->setDefinition(sprintf('revinate_rabbit_mq.consumer.%s', $key), $definition);
        }
    }

    /**
     * Returns Service Container
     */
    protected function getContainer() {
        return new Reference('service_container');
    }

    /**
     * @param $connectionName
     * @return Reference
     */
    protected function getConnection($connectionName) {
        return new Reference(sprintf('revinate_rabbit_mq.connection.%s', $connectionName));
    }

    /**
     * @param $exchangeName
     * @return Reference
     */
    protected function getExchange($exchangeName) {
        return new Reference(sprintf('revinate_rabbit_mq.exchange.%s', $exchangeName));
    }

    /**
     * @param $queueName
     * @return Reference
     */
    protected function getQueue($queueName) {
        return new Reference(sprintf('revinate_rabbit_mq.queue.%s', $queueName));
    }
}
