<?php
namespace Revinate\RabbitMqBundle\Producer;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Encoder\DecoderInterface;
use Revinate\RabbitMqBundle\Encoder\EncoderInterface;
use Revinate\RabbitMqBundle\Encoder\JsonEncoder;
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
    /** @var EncoderInterface */
    protected $encoder;
    /** @var \PhpAmqpLib\Channel\AMQPChannel */
    protected $channel;

    /**
     * @param $name
     * @param Exchange $exchange
     */
    public function __construct($name = null, Exchange $exchange = null) {
        $this->name = $name;
        $this->exchange = $exchange;
        if ($exchange) {
            $this->channel = $this->getExchange()->getConnection()->channel();
        }
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
     * @return \Revinate\RabbitMqBundle\Encoder\EncoderInterface
     */
    public function getEncoder()
    {
        return $this->encoder;
    }

    /**
     * @param \Revinate\RabbitMqBundle\Encoder\EncoderInterface $encoder
     */
    public function setEncoder($encoder)
    {
        $this->encoder = $encoder;
    }

    /**
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @param string|array|Message $data
     * @param string $routingKey
     */
    public function publish($data, $routingKey) {
        $message = $data instanceof Message ? $data : new Message($data, $routingKey);
        $this->basicPublish($message, $routingKey);
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
     * @param Message $message
     * @throws InvalidExchangeConfigurationException
     */
    public function publishToSelf(Message $message) {
        $channel = $message->getQueue()->getChannel();
        $amqpMessage = $this->prepareAMQPMessage($message);
        $channel->basic_publish($amqpMessage, "", $message->getQueue()->getName());
    }

    /**
     * @param Message $message
     * @param $routingKey
     * @throws \Revinate\RabbitMqBundle\Exceptions\InvalidExchangeConfigurationException
     */
    protected function basicPublish(Message $message, $routingKey) {
        $amqpMessage = $this->prepareAMQPMessage($message);
        if (empty($this->exchange)) {
            throw new InvalidExchangeConfigurationException("No exchange found for this producer. Please use setExchange(exchange) or config to specify exchange for this producer.");
        }
        $this->channel->basic_publish($amqpMessage, $this->getExchange()->getName(), $routingKey);
    }

    /**
     * @param Message $message
     * @return AMQPMessage
     */
    protected function prepareAMQPMessage(Message $message) {
        if (! $this->encoder) {
            // Use Default Encoder
            $this->encoder = new JsonEncoder();
        }
        $encodedMessage = $this->encoder->encode($message->getData());
        $properties = array(
            Message::CONTENT_TYPE_PROPERTY => $message->getContentType(),
            Message::DELIVERY_MODE_PROPERTY => $message->getDeliveryMode(),
            Message::APPLICATION_HEADERS_PROPERTY => $message->getHeaders(),
            Message::EXPIRATION_PROPERTY => $message->getExpiration()
        );
        if ($message->getReplyTo()) {
            $properties[Message::REPLY_TO] = $message->getReplyTo();
        }
        return new AMQPMessage($encodedMessage, $properties);
    }
}