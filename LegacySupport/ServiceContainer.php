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
    /** @var Producer[]  */
    protected $producers = array();
    /** @var Exchange[]  */
    protected $exchanges = array();
    /** @var Consumer[]  */
    protected $consumers = array();
    /** @var Queue[]  */
    protected $queues = array();

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
     * @return array
     */
    public function getConfig() {
        return $this->config;
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

        if (is_array($connection['host'])) {
            $hosts = [];
            foreach ($connection['host'] as $host) {
                $hosts[] = [
                    'host' => $host,
                    'port' => $connection['port'],
                    'user' => $connection['user'],
                    'password' => $connection['password'],
                    'vhost' => $connection['vhost']
                ];
            }
            return AMQPStreamConnection::create_connection($hosts);
        }

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
        if (!isset($this->exchanges[$name])) {
            $config = array_merge($this->config['default_exchange'], $this->config['exchanges'][$name]);
            $this->exchanges[$name] = new Exchange(
                $name,
                $this->getConnection($config['connection']),
                $config['type'],
                $config['passive'],
                $config['durable'],
                $config['auto_delete'],
                $config['internal'],
                $config['nowait'],
                $config['arguments'],
                $config['ticket'],
                $config['managed']
            );
        }
        return $this->exchanges[$name];
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
        if (!isset($this->queues[$name])) {
            $config = array_merge($this->config['default_queue'], $this->config['queues'][$name]);
            $this->queues[$name] = new Queue(
                $name,
                $this->getExchange($config['exchange']),
                $config['passive'],
                $config['durable'],
                $config['exclusive'],
                $config['auto_delete'],
                $config['nowait'],
                $config['arguments'],
                $config['routing_keys'],
                $config['ticket'],
                $config['managed']
            );
        }
        return $this->queues[$name];
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
        if (!isset($this->producers[$name])) {
            $config = array_merge($this->config['default_producer'], $this->config['producers'][$name]);
            $this->producers[$name] = new Producer(
                $name,
                $this->getExchange($config['exchange'])
            );
            $this->producers[$name]->setEncoder(new $config['encoder']);
        }
        return $this->producers[$name];
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
        if (!isset($this->consumers[$name])) {
            $config = array_merge($this->config['default_consumer'], $this->config['consumers'][$name]);
            $this->consumers[$name] = new Consumer(null, $name, array($this->getQueue($config['queue'])));
            $this->consumers[$name]->setCallbacks(array(array(new $config['callback'], 'execute')));
            $this->consumers[$name]->setIdleTimeout($config['idle_timeout']);
            $this->consumers[$name]->setBatchSize($config['batch_size']);
            $this->consumers[$name]->setBufferWait($config['buffer_wait']);
            $this->consumers[$name]->setMessageClass($config['message_class']);
            $this->consumers[$name]->setDecoder(new $config['decoder']);

            $defaultQosOptions = array('prefetch_size' => 0, 'prefetch_count' => 1, 'global' => false);
            $config['qos_options'] = isset($config['qos_options']) ? array_replace($defaultQosOptions, $config['qos_options']) : $defaultQosOptions;
            $this->consumers[$name]->setQosOptions($config['qos_options']);
        }
        return $this->consumers[$name];
    }

    /**
     * @param $message
     * @throws \Revinate\RabbitMqBundle\Exceptions\InvalidConfigurationException
     */
    protected function throwError($message) {
        throw new InvalidConfigurationException("Unable to find following setting in config file (" . $this->configFile .") " . $message);
    }
}