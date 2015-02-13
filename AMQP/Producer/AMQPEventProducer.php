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
    /** @var ContainerInterface */
    protected $container;
    /** @var  string */
    protected $name;
    /** @var  AMQPConnection */
    protected $connection;
    /** @var  AMQPExchange */
    protected $exchange;
    /** @var array */
    protected $applicationHeaders = array();

    /**
     * @param ContainerInterface $container
     * @param $name
     * @param string $connection
     * @param string $exchange
     */
    public function __construct(ContainerInterface $container, $name, $connection, $exchange) {
        $this->container = $container;
        $this->name = $name;
        $this->connection = $this->container->get("revinate_rabbit_mq.connection.$connection");
        $this->exchange = $this->container->get("revinate_rabbit_mq.connection.$exchange");
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