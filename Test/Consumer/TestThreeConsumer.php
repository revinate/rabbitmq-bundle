<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Revinate\RabbitMqBundle\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Message\Message;

class TestThreeConsumer implements ConsumerInterface {

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return int
     */
    public function execute(Message $message)
    {
        echo "\nRouting Key:" . $message->getRoutingKey();
        echo "\nMessage: " . $message->getData();
        foreach ($message->getHeaders() as $key => $value) {
            echo "\nHeader: " . $key . " = " . json_encode($value);
        }
        return DeliveryResponse::MSG_ACK;
    }
}