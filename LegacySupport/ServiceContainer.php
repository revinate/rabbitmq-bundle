<?php

namespace Revinate\RabbitMqBundle\LegacySupport;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Revinate\RabbitMqBundle\Consumer\Consumer;
use Revinate\RabbitMqBundle\Exceptions\InvalidConfigurationException;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\Producer\Producer;
use Revinate\RabbitMqBundle\Queue\Queue;
use Symfony\Component\Yaml\Parser;

class ServiceContainer {

    /** @var  string */
    protected $env;

    /** @var  string */
    protected $configFile;

    /**
     * @var array
     */
    protected $config;

    /** @var ServiceContainer */
    protected static $instance;

    /**
     * @param $env
     * @param $configFile
     * @return ServiceContainer
     */
    public static function getInstance($env, $configFile) {
        if (!self::$instance) {
            self::$instance = new ServiceContainer($env, $configFile);
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    protected function __construct($env, $configFile) {
        $this->env = $env;
        $this->configFile = $configFile;
        $parser = new Parser();
        $defaultConfig = $parser->parse(file_get_contents(__DIR__ . "/../Resources/config/legacy_defaults.yml"));
        $this->config = $parser->parse(file_get_contents($configFile));
        $this->config = array_merge($defaultConfig['revinate_rabbit_mq'], $this->config['revinate_rabbit_mq']);
    }

    /**
     * Setup all Exchanges and Queues
     */
    public function setup() {
        echo "\n\nDeclaring Exchanges\n";
        foreach ($this->config['exchanges'] as $name => $config) {
            $exchange = $this->getExchange($name);
            $response = null;
            if (!$exchange->getManaged()) {
                echo "Skipped : ";
            } else {
                $exchange->declareExchange();
                echo "Declared: ";
            }
            echo $exchange->getName() . "\n" ;
        }

        echo "\n\nDeclaring Queues\n";
        foreach ($this->config['queues'] as $name => $config) {
            $queue = $this->getQueue($name);
            $response = null;
            if (!$queue->getManaged()) {
                echo "Skipped : ";
            } else {
                $queue->declareQueue();
                echo "Declared: ";
            }
            echo $queue->getName() . "\n" ;
        }
    }

    /**
     * @param $name
     * @return AMQPStreamConnection
     * @throws \Exception
     */
    public function getConnection($name) {
        if (!isset($this->config['connections'][$name])) {
            $this->throwError("Connection: $name");
        }
        if (!isset($this->config['environment'][$this->env]['connections'][$name])) {
            $this->throwError("Connection: $name");
        }
        $connection = array_merge(
            $this->config['default_connection'],
            $this->config['connections'][$name],
            $this->config['environment'][$this->env]['connections'][$name]
        );
        return new AMQPStreamConnection($connection['host'], $connection['port'], $connection['user'], $connection['password'], $connection['vhost']);
    }

    /**
     * @param $name
     * @return Exchange
     * @throws \Exception
     */
    public function getExchange($name) {
        if (!isset($this->config['exchanges'][$name])) {
            $this->throwError("Exchange: $name");
        }
        $exchange = array_merge($this->config['default_exchange'], $this->config['exchanges'][$name]);
        return new Exchange(
            $name,
            $this->getConnection($exchange['connection']),
            $exchange['type'],
            $exchange['passive'],
            $exchange['durable'],
            $exchange['auto_delete'],
            $exchange['internal'],
            $exchange['nowait'],
            $exchange['arguments'],
            $exchange['ticket'],
            $exchange['managed']
        );
    }

    /**
     * @param $name
     * @return Queue
     * @throws \Exception
     */
    public function getQueue($name) {
        if (!isset($this->config['queues'][$name])) {
            $this->throwError("Queue: $name");
        }
        $queue = array_merge($this->config['default_queue'], $this->config['queues'][$name]);
        return new Queue(
            $name,
            $this->getExchange($queue['exchange']),
            $queue['passive'],
            $queue['durable'],
            $queue['exclusive'],
            $queue['auto_delete'],
            $queue['nowait'],
            $queue['arguments'],
            $queue['routing_keys'],
            $queue['ticket'],
            $queue['managed']
        );
    }

    /**
     * @param $name
     * @return Producer
     * @throws \Exception
     */
    public function getProducer($name) {
        if (!isset($this->config['producers'][$name])) {
            $this->throwError("Producer: $name");
        }
        $producer = array_merge($this->config['default_producer'], $this->config['producers'][$name]);
        $producerInstance = new Producer(
            $name,
            $this->getExchange($producer['exchange'])
        );
        $producerInstance->setEncoder(new $producer['encoder']);
        return $producerInstance;
    }

    /**
     * @param $name
     * @return Consumer
     * @throws \Exception
     */
    public function getConsumer($name) {
        if (!isset($this->config['consumers'][$name])) {
            $this->throwError("Consumer: $name");
        }
        $consumer = array_merge($this->config['default_consumer'], $this->config['consumers'][$name]);
        $consumerInstance = new Consumer(null, $name, array($this->getQueue($consumer['queue'])));
        $consumerInstance->setCallbacks(array(array(new $consumer['callback'], 'execute')));
        $consumerInstance->setIdleTimeout($consumer['idle_timeout']);
        $consumerInstance->setBatchSize($consumer['batch_size']);
        $consumerInstance->setBufferWait($consumer['buffer_wait']);
        $consumerInstance->setMessageClass($consumer['message_class']);
        $consumerInstance->setDecoder(new $consumer['decoder']);
        if (isset($consumer['fairness_algorithm'])) {
            $consumerInstance->setFairnessAlgorithm(new $consumer['fairness_algorithm']);
        }
        if (isset($consumer['batch_size'])) {
            $consumer['qos_options'] = isset($consumer['qos_options']) ? $consumer['qos_options'] :
                array('prefetch_size' => 0, 'prefetch_count' => 0, 'global' => false);
            $consumer['qos_options'] = array_replace($consumer['qos_options'], array('prefetch_count' => $consumer['batch_size']));
        }
        if (array_key_exists('qos_options', $consumer)) {
            $consumerInstance->setQosOptions(
                $consumer['qos_options']['prefetch_size'],
                $consumer['qos_options']['prefetch_count'],
                $consumer['qos_options']['global']
            );
        }
        return $consumerInstance;
    }

    /**
     * @param $message
     * @throws \Revinate\RabbitMqBundle\Exceptions\InvalidConfigurationException
     */
    protected function throwError($message) {
        throw new InvalidConfigurationException("Unable to find following setting in config file (" . $this->configFile .") " . $message);
    }
}