<?php
namespace Revinate\RabbitMqBundle\AMQP\Producer;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Exchange\AMQPExchange;
use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;

/**
 * Class AMQPEventProducer
 * @package Revinate\RabbitMqBundle\AMQP
 */
class AMQPEventProducer {
    /** @var  string */
    protected $name;
    /** @var  AMQPConnection */
    protected $connection;
    /** @var  AMQPExchange */
    protected $exchange;
    /** @var array */
    protected $applicationHeaders = array();

    /**
     * @param $name
     * @param AMQPExchange $exchange
     */
    public function __construct($name, AMQPExchange $exchange) {
        $this->name = $name;
        $this->exchange = $exchange;
        $this->connection = $exchange->getConnection();
    }

    /**
     * @return \PhpAmqpLib\Connection\AMQPConnection
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * @return AMQPExchange
     */
    public function getExchange() {
        return $this->exchange;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param $message
     * @param $eventName
     */
    public function publish($message, $eventName) {
        $routingKey = $eventName;
        if (! $message instanceof AMQPEventMessage) {
            $message = new AMQPEventMessage($message, $routingKey);
        }
        $this->amqpPublish($message, $routingKey);
    }

    /**
     * @param AMQPEventMessage $amqpEventMessage
     * @param null $newRoutingKey New Event Name under which to publish this message
     */
    public function rePublish(AMQPEventMessage $amqpEventMessage, $newRoutingKey = null) {
        $newRoutingKey = $newRoutingKey ? $newRoutingKey : $amqpEventMessage->getRoutingKey();
        $amqpEventMessage->incrementRetryCount();
        $this->amqpPublish($amqpEventMessage, $newRoutingKey);
    }

    /**
     * @param $fairnessKey
     * @param $message
     * @param $eventName
     * @param int $delayUnfairMessagesForMs
     */
    public function fairPublish($fairnessKey, $message, $eventName, $delayUnfairMessagesForMs = 10000) {
        $routingKey = $eventName;
        $amqpEventMessage = new AMQPEventMessage($message, $routingKey);
        $amqpEventMessage->setFairnessKey($fairnessKey);
        $amqpEventMessage->setUnfairnessDelay($delayUnfairMessagesForMs);
        $this->amqpPublish($amqpEventMessage, $routingKey);
    }

    /**
     * @param AMQPEventMessage $amqpEventMessage
     * @param $routingKey
     * @internal param $message
     */
    protected function amqpPublish(AMQPEventMessage $amqpEventMessage, $routingKey) {
        $encodedMessage = json_encode($amqpEventMessage->getMessage());
        $properties = array(
            AMQPEventMessage::CONTENT_TYPE_PROPERTY => $amqpEventMessage->getContentType(),
            AMQPEventMessage::DELIVERY_MODE_PROPERTY => $amqpEventMessage->getDeliveryMode(),
            AMQPEventMessage::APPLICATION_HEADERS_PROPERTY => $amqpEventMessage->getHeaders(),
            AMQPEventMessage::EXPIRATION_PROPERTY => $amqpEventMessage->getExpiration()
        );
        $msg = new AMQPMessage($encodedMessage, $properties);
        $channel = $this->getConnection()->channel();
        $channel->basic_publish($msg, $this->getExchange()->getName(), $routingKey);
    }
}