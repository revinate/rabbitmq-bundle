<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Revinate\RabbitMqBundle\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\Exceptions\RejectDropStopWithErrorException;
use Revinate\RabbitMqBundle\Message\Message;

class TestTenConsumer extends BaseConsumer implements ConsumerInterface {

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return int
     * @throws RejectDropStopWithErrorException
     */
    public function execute(Message $message)
    {
        echo "\nRouting Key:" . $message->getRoutingKey();
        echo "\nMessage: " . $this->toString($message->getData());
        throw new RejectDropStopWithErrorException("Somethings wrong! Consuming has stopped! Here is why!?");
    }
}