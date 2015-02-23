<?php

namespace Revinate\RabbitMqBundle\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Consumer;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Exceptions\RejectRequeueException;
use Revinate\RabbitMqBundle\Exceptions\RejectDropException;

/**
 * Class SingleMessageProcessor
 * @package Revinate\RabbitMqBundle\Consumer\Processor
 */
class SingleMessageProcessor extends BaseMessageProcessor implements MessageProcessorInterface {

    /** @var Consumer  */
    protected $consumer;

    /**
     * @param Consumer $consumer
     */
    public function __construct(Consumer $consumer) {
        $this->consumer = $consumer;
    }
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