<?php
namespace Revinate\RabbitMqBundle\Producer;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Encoder\DecoderInterface;
use Revinate\RabbitMqBundle\Encoder\EncoderHelper;
use Revinate\RabbitMqBundle\Encoder\EncoderInterface;
use Revinate\RabbitMqBundle\Encoder\JsonEncoder;
use Revinate\RabbitMqBundle\Exceptions\InvalidExchangeConfigurationException;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\LegacySupport\ServiceContainer;
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
    /** @var  string */
    protected $channelId;
    /** @var  AMQPConnection */
    protected $connection;

    /**
     * @param $name
     * @param Exchange $exchange
     */
    public function __construct($name = null, Exchange $exchange = null) {
        $this->name = $name;
        $this->exchange = $exchange;
        if ($exchange) {
            $this->connection = $this->getExchange()->getConnection();
            $this->channelId = $this->connection->get_free_channel_id();
        }
    }

    /**
     * @return Exchange
     */
    public function getExchange() {
        return $this->exchange;
    }

    /**
     * @return AMQPConnection
     */
    public function getConnection() {
        return $this->connection;
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
    public function getChannel() {
        if (!$this->connection->isConnected()) {
            $this->connection->reconnect();
        }
        return $this->connection->channel($this->channelId);
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
        $amqpMessage = $this->encodeAndGetAMQPMessage($message);
        $channel->basic_publish($amqpMessage, "", $message->getQueue()->getName());
    }

    /**
     * @param Message $message
     * @param $queueName
     * @throws InvalidExchangeConfigurationException
     */
    public function publishToQueue(Message $message, $queueName) {
        $amqpMessage = $this->encodeAndGetAMQPMessage($message);
        $this->getChannel()->basic_publish($amqpMessage, "", $queueName);
    }

    /**
     * @param Message $message
     * @param $routingKey
     * @throws \Revinate\RabbitMqBundle\Exceptions\InvalidExchangeConfigurationException
     */
    protected function basicPublish(Message $message, $routingKey) {
        $amqpMessage = $this->encodeAndGetAMQPMessage($message);
        if (empty($this->exchange)) {
            throw new InvalidExchangeConfigurationException("No exchange found for this producer. Please use setExchange(exchange) or config to specify exchange for this producer.");
        }
        $this->getChannel()->basic_publish($amqpMessage, $this->getExchange()->getName(), $routingKey);
    }

    /**
     * @param Message $message
     * @return AMQPMessage
     */
    protected function encodeAndGetAMQPMessage(Message $message) {
        if (! $this->encoder) {
            // Use Default Encoder
            $this->encoder = EncoderHelper::getDefaultEncoder();
        }
        $encodedMessage = $this->encoder->encode($message->getData());
        return new AMQPMessage($encodedMessage, self::getAMQPProperties($message));
    }

    /**
     * @param Message $message
     * @return array
     */
    public static function getAMQPProperties(Message $message) {
        $properties = array(
            Message::CONTENT_TYPE_PROPERTY => $message->getContentType(),
            Message::DELIVERY_MODE_PROPERTY => $message->getDeliveryMode(),
            Message::APPLICATION_HEADERS_PROPERTY => $message->getHeaders(),
            Message::EXPIRATION_PROPERTY => $message->getExpiration()
        );
        if ($message->getReplyTo()) {
            $properties[Message::REPLY_TO] = $message->getReplyTo();
        }
        return $properties;
    }
}