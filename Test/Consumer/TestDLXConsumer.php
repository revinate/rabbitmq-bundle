<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Revinate\RabbitMqBundle\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Exceptions\RejectDropWithErrorException;
use Revinate\RabbitMqBundle\Message\Message;

class TestDLXConsumer extends BaseConsumer implements ConsumerInterface {

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return int
     * @throws RejectDropWithErrorException
     */
    public function execute(Message $message) {
        echo "\nRouting Key:" . $message->getRoutingKey();
        echo "\nError: " . $message->getHeader("x-exception-message");
        return DeliveryResponse::MSG_ACK;
    }
}