<?php

namespace Revinate\RabbitMqBundle\AMQP\EventListeners\Examples;

use Revinate\RabbitMqBundle\AMQP\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\AMQP\EventListeners\Types\EventBaseAMQPListener;
use Revinate\RabbitMqBundle\AMQP\Exceptions\RejectDropException;
use Revinate\RabbitMqBundle\AMQP\Exceptions\RejectRequeueException;
use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;

class LogEventAMQPListener extends EventBaseAMQPListener implements ConsumerInterface {

    /**
     * @param AMQPEventMessage $amqpMessage
     * @throws RejectDropException|RejectRequeueException
     * @return void
     */
    public function process(AMQPEventMessage $amqpMessage) {
        // throw new RejectDropException();
        print_r($amqpMessage->getMessage());
        //$this->getProducer()->publish('this is another log', EventType::R_ERROR_LOG);
        //$this->getProducer()->rePublishForLater($message, 60000);
    }
}