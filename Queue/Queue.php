<?php

namespace Revinate\RabbitMqBundle\Queue;

use PhpAmqpLib\Connection\AMQPConnection;
use Revinate\RabbitMqBundle\Exceptions\InvalidQueueConfigurationException;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Queue {
    /** @var  string */
    protected $name;
    /** @var  AMQPConnection */
    protected $connection;
    /** @var Exchange */
    protected $exchange;
    /** @var  boolean */
    protected $passive = false;
    /** @var  boolean */
    protected $durable = true;
    /** @var  boolean */
    protected $exclusive = false;
    /** @var  boolean */
    protected $autoDelete = false;
    /** @var boolean */
    protected $noWait = false;
    /** @var Array */
    protected $arguments = null;
    /** @var  Array */
    protected $routingKeys = array();
    /** @var  string */
    protected $ticket = null;
    /** @var  boolean */
    protected $isDeclared = false;

    /**
     * @param $name
     * @param $exchange
     * @param $passive
     * @param $durable
     * @param $exlusive
     * @param $autoDelete
     * @param $noWait
     * @param $arguments
     * @param $routingKeys
     * @param $ticket
     * @throws InvalidQueueConfigurationException
     */
    public function __construct($name, Exchange $exchange, $passive, $durable, $exlusive, $autoDelete, $noWait, $arguments, $routingKeys, $ticket) {
        if (empty($name) || empty($exchange)) {
            throw new InvalidQueueConfigurationException("Please specify Queue name and exchange to declare a queue.");
        }
        $this->connection = $exchange->getConnection();
        $this->exchange = $exchange;
        $this->name = $name;
        $this->passive = $passive;
        $this->durable = $durable;
        $this->exclusive = $exlusive;
        $this->autoDelete = $autoDelete;
        $this->noWait = $noWait;
        $this->arguments = $arguments;
        $this->routingKeys = $routingKeys;
        $this->ticket = $ticket;
        $this->declareQueue();
    }

    /**
     * Declare Queue
     */
    public function declareQueue() {
        $channel = $this->connection->channel();
        list($queueName, , ) = $channel->queue_declare(
            $this->getName(),
            $this->getPassive(),
            $this->getDurable(),
            $this->getExclusive(),
            $this->getAutoDelete(),
            $this->getNoWait(),
            $this->getArguments(),
            $this->getTicket()
        );

         if (count($this->getRoutingKeys()) > 0) {
             foreach ($this->getRoutingKeys() as $routingKey) {
                 $channel->queue_bind($queueName, $this->getExchange()->getName(), $routingKey);
             }
         } else {
             $channel->queue_bind($queueName, $this->getExchange()->getName(), '');
         }
        $this->isDeclared = true;
    }


    /**
     * @param Array $arguments
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;
    }

    /**
     * @return Array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param boolean $autoDelete
     */
    public function setAutoDelete($autoDelete)
    {
        $this->autoDelete = $autoDelete;
    }

    /**
     * @return boolean
     */
    public function getAutoDelete()
    {
        return $this->autoDelete;
    }

    /**
     * @param boolean $durable
     */
    public function setDurable($durable)
    {
        $this->durable = $durable;
    }

    /**
     * @return boolean
     */
    public function getDurable()
    {
        return $this->durable;
    }

    /**
     * @param boolean $exclusive
     */
    public function setExclusive($exclusive)
    {
        $this->exclusive = $exclusive;
    }

    /**
     * @return boolean
     */
    public function getExclusive()
    {
        return $this->exclusive;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param boolean $noWait
     */
    public function setNoWait($noWait)
    {
        $this->noWait = $noWait;
    }

    /**
     * @return boolean
     */
    public function getNoWait()
    {
        return $this->noWait;
    }

    /**
     * @param boolean $passive
     */
    public function setPassive($passive)
    {
        $this->passive = $passive;
    }

    /**
     * @return boolean
     */
    public function getPassive()
    {
        return $this->passive;
    }

    /**
     * @param Array $routingKeys
     */
    public function setRoutingKeys($routingKeys)
    {
        $this->routingKeys = $routingKeys;
    }

    /**
     * @return string
     */
    public function getTicket()
    {
        return $this->ticket;
    }

    /**
     * @return Array
     */
    public function getRoutingKeys()
    {
        return $this->routingKeys;
    }

    /**
     * @return \PhpAmqpLib\Connection\AMQPConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return boolean
     */
    public function getIsDeclared()
    {
        return $this->isDeclared;
    }

    /**
     * @return \Revinate\RabbitMqBundle\Exchange\Exchange
     */
    public function getExchange()
    {
        return $this->exchange;
    }
}