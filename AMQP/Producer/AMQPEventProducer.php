<?php
namespace Revinate\RabbitMqBundle\AMQP\Producer;

use OldSound\RabbitMqBundle\RabbitMq\BaseAmqp;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Exception\AMQPProtocolException;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Exchange\AMQPExchange;
use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
        $this->exchange = $exchange->getConnection();
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
     * @param null $newEventName New Event Name under which to publish this message
     */
    public function rePublish(AMQPEventMessage $amqpEventMessage, $newEventName = null) {
        $newEventName = $newEventName ? $newEventName : $amqpEventMessage->getRoutingKey();
        $this->amqpPublish($amqpEventMessage, $newEventName);
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
     * @param int $requeueInMs Requeue in these many millisecs
     * @deprecated
     */
    public function rePublishForLater(AMQPEventMessage $amqpEventMessage, $requeueInMs) {
        //$amqpEventMessage->setExpiration($requeueInMs);
        $this->amqpPublish($amqpEventMessage, $amqpEventMessage->getRoutingKey());
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