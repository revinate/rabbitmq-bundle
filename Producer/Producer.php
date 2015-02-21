<?php
namespace Revinate\RabbitMqBundle\Producer;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\Message\Message;

/**
 * Class Producer
 * @package Revinate\RabbitMqBundle
 */
class Producer {
    /** @var  string */
    protected $name;
    /** @var  AMQPConnection */
    protected $connection;
    /** @var  Exchange */
    protected $exchange;
    /** @var array */
    protected $applicationHeaders = array();

    /**
     * @param $name
     * @param Exchange $exchange
     */
    public function __construct($name, Exchange $exchange) {
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
     * @return Exchange
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
     * @param array $data
     * @param string $routingKey
     */
    public function publish($data, $routingKey) {
        if (! $data instanceof Message) {
            $message = new Message($data, $routingKey);
        }
        $this->basicPublish($message, $routingKey);
    }

    /**
     * @param Message $message
     */
    public function rePublishForSelf(Message $message) {
        $newRoutingKey = "queue." . $message->getConsumer()->getQueue()->getName();
        $message->incrementRetryCount();
        $this->basicPublish($message, $newRoutingKey);
    }

    /**
     * @param Message $message
     * @param null $newRoutingKey New Event Name under which to publish this message
     */
    public function rePublishForAll(Message $message, $newRoutingKey = null) {
        $newRoutingKey = $newRoutingKey ? $newRoutingKey : $message->getRoutingKey();
        $message->incrementRetryCount();
        $this->basicPublish($message, $newRoutingKey);
    }

    /**
     * @param $fairnessKey
     * @param $data
     * @param $eventName
     * @param int $delayUnfairMessagesForMs
     */
    public function fairPublish($fairnessKey, $data, $eventName, $delayUnfairMessagesForMs = 10000) {
        $routingKey = $eventName;
        $message = new Message($data, $routingKey);
        $message->setFairnessKey($fairnessKey);
        $message->setUnfairnessDelay($delayUnfairMessagesForMs);
        $this->basicPublish($message, $routingKey);
    }

    /**
     * @param Message $message
     * @param $routingKey
     * @internal param $message
     */
    protected function basicPublish(Message $message, $routingKey) {
        $encodedMessage = json_encode($message->getData());
        $properties = array(
            Message::CONTENT_TYPE_PROPERTY => $message->getContentType(),
            Message::DELIVERY_MODE_PROPERTY => $message->getDeliveryMode(),
            Message::APPLICATION_HEADERS_PROPERTY => $message->getHeaders(),
            Message::EXPIRATION_PROPERTY => $message->getExpiration()
        );
        $msg = new AMQPMessage($encodedMessage, $properties);
        $channel = $this->getConnection()->channel();
        $channel->basic_publish($msg, $this->getExchange()->getName(), $routingKey);
    }
}