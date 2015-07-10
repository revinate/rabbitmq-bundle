<?php

namespace Revinate\RabbitMqBundle\Exchange;
use PhpAmqpLib\Connection\AMQPConnection;
use Revinate\RabbitMqBundle\Exceptions\InvalidExchangeConfigurationException;

/**
 * Class Exchange
 * Doc: http://www.rabbitmq.com/amqp-0-9-1-reference.html#exchange.bind
 */
class Exchange {

    /** @var AMQPConnection  */
    protected $connection;
    /** @var string */
    protected $name;
    /** @var string */
    protected $type;
    /** @var bool */
    protected $passive = false;
    /** @var bool  */
    protected $durable = true;
    /** @var bool  */
    protected $autoDelete = false;
    /** @var bool  */
    protected $internal = false;
    /** @var bool  */
    protected $noWait = false;
    /** @var array */
    protected $arguments = null;
    /** @var string */
    protected $ticket = null;
    /** @var bool  */
    protected $isDeclared = false;
    /** @var bool  */
    protected $managed = true;

    /**
     * @param $name
     * @param $connection
     * @param $type
     * @param $passive
     * @param $durable
     * @param $autoDelete
     * @param $internal
     * @param $noWait
     * @param $arguments
     * @param $ticket
     * @param $managed
     * @throws \Revinate\RabbitMqBundle\Exceptions\InvalidExchangeConfigurationException
     */
    function __construct($name, $connection, $type, $passive, $durable, $autoDelete, $internal, $noWait, $arguments, $ticket, $managed) {
        if (empty($name) || empty($type)) {
            throw new InvalidExchangeConfigurationException("Please specify Exchange name and type to declare an exchange.");
        }
        $this->connection = $connection;
        $this->name = $name;
        $this->type = $type;
        $this->passive = $passive;
        $this->durable = $durable;
        $this->autoDelete = $autoDelete;
        $this->internal = $internal;
        $this->noWait = $noWait;
        $this->arguments = $arguments;
        $this->ticket = $ticket;
        $this->managed = $managed;
    }

    /**
     * Declare Exchange
     */
    public function declareExchange() {
        $response = $this->connection->channel()->exchange_declare(
            $this->getName(),
            $this->getType(),
            $this->getPassive(),
            $this->getDurable(),
            $this->getAutoDelete(),
            $this->getInternal(),
            $this->getNoWait(),
            $this->getArguments(),
            $this->getTicket()
        );
        $this->isDeclared = true;
        return $response;
    }

    /**
     * Delete Exchange
     */
    public function deleteExchange() {
        $this->connection->channel()->exchange_delete($this->getName());
    }

    /**
     * @return Array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @return boolean
     */
    public function getAutoDelete()
    {
        return $this->autoDelete;
    }

    /**
     * @return boolean
     */
    public function getDurable()
    {
        return $this->durable;
    }

    /**
     * @return boolean
     */
    public function getInternal()
    {
        return $this->internal;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return boolean
     */
    public function getNoWait()
    {
        return $this->noWait;
    }

    /**
     * @return boolean
     */
    public function getPassive()
    {
        return $this->passive;
    }

    /**
     * @return string
     */
    public function getTicket()
    {
        return $this->ticket;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return \PhpAmqpLib\Connection\AMQPConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return mixed
     */
    public function getIsDeclared()
    {
        return $this->isDeclared;
    }

    /**
     * @return boolean
     */
    public function getManaged()
    {
        return $this->managed;
    }
}