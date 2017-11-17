<?php

namespace Revinate\RabbitMqBundle\DependencyInjection;

use Revinate\RabbitMqBundle\Exceptions\MissingCallbacksForConsumerException;
use Revinate\RabbitMqBundle\Exceptions\NoCallbacksConfiguredForConsumerException;
use Revinate\RabbitMqBundle\Exceptions\NoQueuesConfiguredForConsumerException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Extension\Extension;
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
        foreach ($this->config['connections'] as $key => $config) {
            $classParam =
                $config['lazy']
                    ? '%revinate_rabbit_mq.lazy.connection.class%'
                    : '%revinate_rabbit_mq.connection.class%';
            //@see PhpAmqpLib\Connection\AMQPStreamConnection::__construct()
            $definition = new Definition($classParam, array(
                $config['host'],
                $config['port'],
                $config['user'],
                $config['password'],
                $config['vhost'],
                $config['insist'],
                $config['login_method'],
                $config['login_response'],
                $config['locale'],
                $config['connection_timeout'],
                $config['read_write_timeout'],
                $config['context'],
                $config['keepalive'],
                $config['heartbeat']
            ));
            $definition->setLazy(true);

            $this->container->setDefinition(sprintf('revinate_rabbit_mq.connection.%s', $key), $definition);
        }
    }

    /**
     * Load Exchanges
     */
    protected function loadExchanges() {
        $servicesDefinition = $this->container->getDefinition('revinate.rabbit_mq.services');
        foreach ($this->config['exchanges'] as $key => $config) {
            $definition = new Definition('%revinate_rabbit_mq.exchange.class%', array(
                $key,
                $this->getConnection($config['connection']),
                $config['type'],
                $config['passive'],
                $config['durable'],
                $config['auto_delete'],
                $config['internal'],
                $config['nowait'],
                $config['arguments'],
                $config['ticket'],
                $config['managed'],
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
        foreach ($this->config['queues'] as $key => $config) {
            $definition = new Definition('%revinate_rabbit_mq.queue.class%', array(
                $key,
                $this->getExchange($config['exchange']),
                $config['passive'],
                $config['durable'],
                $config['exclusive'],
                $config['auto_delete'],
                $config['nowait'],
                $config['arguments'],
                $config['routing_keys'],
                $config['ticket'],
                $config['managed'],
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
            if ($this->container->hasParameter('revinate_rabbit_mq.enable_mock_producer')
                && $this->container->getParameter('revinate_rabbit_mq.enable_mock_producer')) {
                $definition = new Definition('%revinate_rabbit_mq.mock_producer.class%', array(
                    $key
                ));
            } else {
                $definition = new Definition('%revinate_rabbit_mq.producer.class%', array(
                    $key,
                    $this->getExchange($producer['exchange'])
                ));
            }

            $definition->setLazy(true);
            $definition->addMethodCall('setEncoder', array(new Reference($producer['encoder'])));
            $this->container->setDefinition(sprintf('revinate_rabbit_mq.producer.%s', $key), $definition);
        }
    }

    /**
     * Load Consumers
     */
    protected function loadConsumers() {
        foreach ($this->config['consumers'] as $key => $config) {
            $queueNames = array();
            $callbackNames = array();
            if (! is_null($config['queue'])) {
                $queueNames[] = $config['queue'];
            } elseif (! empty($config['queues'])) {
                $queueNames = $config['queues'];
            }
            if (! is_null($config['callback'])) {
                $callbackNames[] = $config['callback'];
            } elseif (! empty($config['callbacks'])) {
                $callbackNames = $config['callbacks'];
            }
            if (isset($config['mappings'])) {
                foreach ($config['mappings'] as $mapping) {
                    $callbackNames[] = $mapping['callback'];
                    $queueNames[] = $mapping['queue'];
                }
            }
            if (empty($queueNames)) {
                throw new NoQueuesConfiguredForConsumerException(__METHOD__ . " $key: This consumer is not configured to listen to any queues.");
            }
            if (empty($callbackNames)) {
                throw new NoCallbacksConfiguredForConsumerException(__METHOD__ . " $key: This consumer is not configured with any callbacks.");
            }
            if (count($queueNames) != count($callbackNames)) {
                throw new MissingCallbacksForConsumerException(__METHOD__ . " $key: Some queues are missing callbacks");
            }
            $definition = new Definition('%revinate_rabbit_mq.consumer.class%', array(
                $this->getContainer(),
                $key,
                $this->getQueues($queueNames),
            ));
            $definition->addMethodCall('setCallbacks', array($this->getCallbacks($callbackNames, 'execute')));
            $definition->addMethodCall('setSetContainerCallbacks', array($this->getCallbacks($callbackNames, 'setContainer')));
            $definition->addMethodCall('setIdleTimeout', array($config['idle_timeout']));
            $definition->addMethodCall('setBatchSize', array($config['batch_size']));
            $definition->addMethodCall('setMessageClass', array($config['message_class']));
            $definition->addMethodCall('setBufferWait', array($config['buffer_wait']));
            $definition->addMethodCall('setDecoder', array(new Reference($config['decoder'])));

            $defaultQosOptions =  array('prefetch_size' => 0, 'prefetch_count' => 1, 'global' => false);
            $config['qos_options'] = isset($config['qos_options']) ? array_replace($defaultQosOptions, $config['qos_options']) : $defaultQosOptions;
            $definition->addMethodCall('setQosOptions', array($config['qos_options']));

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
     * @param string[] $queueNames
     * @return Reference
     */
    protected function getQueues($queueNames) {
        $queues = array();
        foreach ($queueNames as $queueName) {
            $queues[] = new Reference(sprintf('revinate_rabbit_mq.queue.%s', $queueName));
        }
        return $queues;
    }

    /**
     * @param $callbackNames
     * @param $methodName
     * @return array
     */
    protected function getCallbacks($callbackNames, $methodName) {
        $callbacks = array();
        foreach ($callbackNames as $callbackName) {
            $callbacks[] = array(new Reference($callbackName), $methodName);
        }
        return $callbacks;
    }
}
