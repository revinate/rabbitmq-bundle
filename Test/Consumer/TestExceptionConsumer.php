<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Revinate\RabbitMqBundle\Consumer\BatchConsumerInterface;
use Revinate\RabbitMqBundle\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Message\Message;

class TestExceptionConsumer implements BatchConsumerInterface {

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message[] $messages
     * @return array
     * @throws \Exception
     */
    public function execute($messages)
    {
        $status = array();
        foreach ($messages as $index => $message) {
            echo "\nRouting Key:" . $message->getRoutingKey();
            echo "\nMessage: " . $this->toString($message->getData());
            echo "-" . $index;
            if ($index > 5) {
                echo "\nThis is an exception";
                throw new \Exception("This is an exception");
            }
            $status[] = DeliveryResponse::MSG_ACK;
        }
        return $status;
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