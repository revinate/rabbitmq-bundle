<?php
namespace Revinate\RabbitMqBundle\Producer;

use PhpAmqpLib\Channel\AMQPChannel;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\Message\Message;

/**
 * Class DefaultExchangeProducer
 * @package Revinate\RabbitMqBundle
 */
class DefaultExchangeProducer extends Producer {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(null, null);
    }

    /**
     * @param $channel
     */
    public function setChannel(AMQPChannel $channel) {
        $this->channel = $channel;
    }

    /**
     * @param Message $message
     * @param string $routingKey
     */
    public function publishToDefaultExchange(Message $message, $routingKey) {
        $amqpMessage = $this->prepareAMQPMessage($message);
        $this->channel->basic_publish($amqpMessage, "", $routingKey);
    }
}