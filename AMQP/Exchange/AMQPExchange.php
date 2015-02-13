<?php

namespace Revinate\RabbitMqBundle\AMQP\Exchange;
use PhpAmqpLib\Connection\AMQPConnection;
use Revinate\RabbitMqBundle\AMQP\Exceptions\InvalidExchangeConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AMQPExchange
 * Doc: http://www.rabbitmq.com/amqp-0-9-1-reference.html#exchange.bind
 */
class AMQPExchange {

    /**
     * @var ContainerInterface
     */
    protected $container;
    /**
     * @var AMQPConnection
     */
    protected $connection;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var string
     */
    protected $type;
    /**
     * @var boolean
     */
    protected $passive = false;
    /**
     * @var boolean
     */
    protected $durable = true;
    /**
     * @var boolean
     */
    protected $autoDelete = false;
    /**
     * @var boolean
     */
    protected $internal = false;
    /**
     * @var boolean
     */
    protected $noWait = false;
    /**
     * @var Array
     */
    protected $arguments = null;
    /**
     * @var string
     */
    protected $ticket = null;
    /**
     * @var
     */
    protected $isDeclared = false;

    /**
     * @param ContainerInterface $container
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
     * @throws InvalidExchangeConfigurationException
     */
    function __construct(ContainerInterface $container, $name, $connection, $type, $passive, $durable, $autoDelete, $internal, $noWait, $arguments, $ticket) {
        if (empty($name) || empty($type)) {
            throw new InvalidExchangeConfigurationException("Please specify Exchange name and type to declare an exchange.");
        }
        $this->container = $container;
        $this->connection = $this->container->get("revinate_rabbit_mq.connection.$connection");
        $this->name = $name;
        $this->type = $type;
        $this->passive = $passive;
        $this->durable = $durable;
        $this->autoDelete = $autoDelete;
        $this->internal = $internal;
        $this->noWait = $noWait;
        $this->arguments = $arguments;
        $this->ticket = $ticket;
        $this->declareExchange();
    }

    /**
     * Declare Exchange
     */
    public function declareExchange() {
        $this->connection->channel()->exchange_declare(
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
}