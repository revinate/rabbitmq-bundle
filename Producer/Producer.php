<?php
namespace Revinate\RabbitMqBundle\Producer;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Exceptions\InvalidExchangeConfigurationException;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\Message\Message;

/**
 * Class Producer
 * @package Revinate\RabbitMqBundle
 */
class Producer {
    /** @var  string */
    protected $name;
    /** @var  Exchange */
    protected $exchange;
    /** @var array */
    protected $applicationHeaders = array();

    /**
     * @param $name
     * @param Exchange $exchange
     */
    public function __construct($name = null, Exchange $exchange = null) {
        $this->name = $name;
        $this->exchange = $exchange;
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
     * @param array|Message $data
     * @param string $routingKey
     */
    public function publish($data, $routingKey) {
        $message = $data instanceof Message ? $data : new Message($data, $routingKey);
        $this->basicPublish($message, $routingKey);
    }

    /**
     * @param Message $message
     */
    public function rePublish(Message $message) {
        // Change the routing key so that only current consumer gets this message, not other consumers of original routing key.
        $newRoutingKey = "queue." . $message->getConsumer()->getQueue()->getName();
        $message->incrementRetryCount();
        $this->basicPublish($message, $newRoutingKey);
    }

    /**
     * @param Message $message
     * @param null $newRoutingKey New Event Name under which to publish this message
     * @deprecated You should not be publishing for all consumers. Use republish() instead
     */
    protected  function rePublishForAll(Message $message, $newRoutingKey = null) {
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
     * @throws \Revinate\RabbitMqBundle\Exceptions\InvalidExchangeConfigurationException
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
        if (empty($this->exchange)) {
            throw new InvalidExchangeConfigurationException("No exchange found for this producer. Please use setExchange(exchange) or config to speficify exchange for this producer.");
        }
        $channel = $this->getExchange()->getConnection()->channel();
        $channel->basic_publish($msg, $this->getExchange()->getName(), $routingKey);
    }

}