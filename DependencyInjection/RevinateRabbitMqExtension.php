<?php

namespace Revinate\RabbitMqBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
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

    public function load(array $configs, ContainerBuilder $container) {
        $this->container = $container;
        $configuration = new Configuration();
        $this->config = $this->processConfiguration($configuration, $configs);
        error_log(print_r($this->config, true));

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
        foreach ($this->config['exchanges'] as $key => $exchange) {
            $definition = new Definition('%revinate_rabbit_mq.exchange.class%', array(
                $this->container,
                $key,
                $exchange['connection'],
                $exchange['type'],
                $exchange['passive'],
                $exchange['durable'],
                $exchange['auto_delete'],
                $exchange['internal'],
                $exchange['nowait'],
                $exchange['arguments'],
                $exchange['ticket'],
            ));
            $this->container->setDefinition(sprintf('revinate_rabbit_mq.exchange.%s', $key), $definition);
        }
    }

    /**
     * Load Queues
     */
    protected function loadQueues() {
        foreach ($this->config['queues'] as $key => $queue) {
            $definition = new Definition('%revinate_rabbit_mq.queue.class%', array(
                $this->container,
                $key,
                $queue['connection'],
                $queue['exchange'],
                $queue['passive'],
                $queue['durable'],
                $queue['exclusive'],
                $queue['auto_delete'],
                $queue['nowait'],
                $queue['arguments'],
                $queue['routing_keys'],
            ));
            $this->container->setDefinition(sprintf('revinate_rabbit_mq.queue.%s', $key), $definition);
        }
    }

    /**
     * Load Producers
     */
    protected function loadProducers() {
        foreach ($this->config['producers'] as $key => $producer) {
            $definition = new Definition('%revinate_rabbit_mq.producer.class%', array(
                $this->container,
                $key,
                $producer['connection'],
                $producer['exchange'],
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
                $this->container,
                $key,
                $consumer['connection'],
                $consumer['queue']
            ));
            $definition->addMethodCall('setCallback', array(array(new Reference($consumer['callback']), 'execute')));
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
}
