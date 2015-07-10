<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Revinate\RabbitMqBundle\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Message\Message;

class RejectConsumer implements ConsumerInterface {

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return int
     */
    public function execute(Message $message)
    {
        echo "\nRouting Key:" . $message->getRoutingKey();
        echo "\nMessage: " . $this->toString($message->getData());
        return DeliveryResponse::MSG_REJECT;
    }

    protected function toString($data) {
        if (is_array($data)) {
            return json_encode($data);
        }
        if (is_object($data)) {
            return serialize($data);
        }
        return $data;
    }
}