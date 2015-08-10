<?php

namespace Revinate\RabbitMqBundle\Queue;

use PhpAmqpLib\Connection\AMQPConnection;
use Revinate\RabbitMqBundle\Exceptions\InvalidQueueConfigurationException;
use Revinate\RabbitMqBundle\Exchange\Exchange;

/**
 * Class Queue
 * @package Revinate\RabbitMqBundle\Queue
 */
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
    /** @var bool  */
    protected $managed = true;

    /**
     * @param $name
     * @param \Revinate\RabbitMqBundle\Exchange\Exchange $exchange
     * @param $passive
     * @param $durable
     * @param $exclusive
     * @param $autoDelete
     * @param $noWait
     * @param $arguments
     * @param array() $routingKeys
     * @param $ticket
     * @param $managed
     * @throws \Revinate\RabbitMqBundle\Exceptions\InvalidQueueConfigurationException
     */
    public function __construct($name, Exchange $exchange, $passive, $durable, $exclusive, $autoDelete, $noWait, $arguments, $routingKeys, $ticket, $managed) {
        if (empty($name) || empty($exchange)) {
            throw new InvalidQueueConfigurationException("Please specify Queue name and exchange to declare a queue.");
        }
        $this->connection = $exchange->getConnection();
        $this->channel = $this->connection->channel();
        $this->exchange = $exchange;
        $this->name = $name;
        $this->passive = $passive;
        $this->durable = $durable;
        $this->exclusive = $exclusive;
        $this->autoDelete = $autoDelete;
        $this->noWait = $noWait;
        $this->arguments = $arguments;
        $this->routingKeys = $routingKeys;
        $this->ticket = $ticket;
        $this->managed = $managed;
    }

    /**
     * Declare Queue
     */
    public function declareQueue() {
        $channel = $this->connection->channel();
        $response = $channel->queue_declare(
            $this->getName(),
            $this->getPassive(),
            $this->getDurable(),
            $this->getExclusive(),
            $this->getAutoDelete(),
            $this->getNoWait(),
            $this->getArguments(),
            $this->getTicket()
        );
        $queueName = $response[0];
         if (count($this->getRoutingKeys()) > 0) {
             foreach ($this->getRoutingKeys() as $routingKey) {
                 $channel->queue_bind($queueName, $this->getExchange()->getName(), $routingKey);
             }
         } else {
             $channel->queue_bind($queueName, $this->getExchange()->getName(), '');
         }
        $this->isDeclared = true;
        return $response;
    }

    /**
     * Delete Queue
     */
    public function deleteQueue() {
        $this->connection->channel()->queue_delete($this->getName());
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
     * @param string[] $routingKeys
     */
    public function setRoutingKeys($routingKeys)
    {
        $this->routingKeys = $routingKeys;
    }

    /**
     * @param string $routingKey
     */
    public function addRoutingKey($routingKey) {
        $this->routingKeys[] = $routingKey;
    }

    /**
     * @return string
     */
    public function getTicket()
    {
        return $this->ticket;
    }

    /**
     * @return string[]
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
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    public function getChannel() {
        return $this->channel;
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

    /**
     * @return boolean
     */
    public function getManaged()
    {
        return $this->managed;
    }
}