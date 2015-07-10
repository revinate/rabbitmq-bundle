<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Revinate\RabbitMqBundle\Consumer\BatchConsumerInterface;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Message\Message;

class TestTwoConsumer implements BatchConsumerInterface {

    /**
     * @param Message[] $messages
     * @return int|void
     */
    public function execute($messages)
    {
        $returnCodes = array();
        foreach ($messages as $message) {
            echo "\nRouting Key:" . $message->getRoutingKey();
            echo "\nMessage: " . $message->getData();
            $returnCodes[] = DeliveryResponse::MSG_ACK;
        }
        echo "\nReturning from Bulk execute";
        return $returnCodes;
    }
}