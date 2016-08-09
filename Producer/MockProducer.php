<?php
namespace Revinate\RabbitMqBundle\Producer;

use Revinate\RabbitMqBundle\Exceptions\InvalidExchangeConfigurationException;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\Message\Message;
use SplQueue;

/**
 * Class MockProducer
 * @package Revinate\RabbitMqBundle
 */
class MockProducer extends Producer {

    /** @var SplQueue */
    protected $queue;

    /**
     * @override
     *
     * @param $name
     * @param Exchange $exchange
     */
    public function __construct($name = null, Exchange $exchange = null) {
        parent::__construct(null, null);
        $this->queue = new SplQueue();
    }

    /**
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @param Exchange $exchange
     */
    public function setExchange(Exchange $exchange) {
        $this->exchange = $exchange;
    }

    /**
     * @override
     *
     * @param Message $message
     * @throws InvalidExchangeConfigurationException
     */
    public function publishToSelf(Message $message) {
        $this->basicPublish($message, "mock_key");
    }

    /**
     * @override
     *
     * @param Message $message
     * @param $routingKey
     * @throws \Revinate\RabbitMqBundle\Exceptions\InvalidExchangeConfigurationException
     */
    protected function basicPublish(Message $message, $routingKey) {
        $this->queue->enqueue($message);
    }

    /**
     * to assert the actual publish
     *
     * @return Message|array
     */
    public function consume() {
        return $this->queue->dequeue();
    }

    /**
     * @return bool
     */
    public function isEmpty() {
        return $this->queue->isEmpty();
    }
}
