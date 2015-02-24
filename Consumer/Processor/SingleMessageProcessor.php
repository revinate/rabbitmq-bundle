<?php

namespace Revinate\RabbitMqBundle\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Consumer;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Exceptions\RejectRequeueException;
use Revinate\RabbitMqBundle\Exceptions\RejectDropException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SingleMessageProcessor
 * @package Revinate\RabbitMqBundle\Consumer\Processor
 */
class SingleMessageProcessor extends BaseMessageProcessor implements MessageProcessorInterface {

    /**
     * @param AMQPMessage $amqpMessage
     * @return mixed|void
     */
    public function processMessage(AMQPMessage $amqpMessage) {
        $processFlag = $this->callConsumerCallback(array($amqpMessage));
        $this->consumer->ackOrNackMessage($amqpMessage, $processFlag);
        $this->consumer->stopConsumerIfTargetReached();
    }
}