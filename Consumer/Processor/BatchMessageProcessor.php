<?php

namespace Revinate\RabbitMqBundle\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Consumer;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Exceptions\RejectRequeueException;
use Revinate\RabbitMqBundle\Exceptions\RejectDropException;
use Revinate\RabbitMqBundle\Message\Message;

/**
 * Class BatchMessageProcessor
 * @package Revinate\RabbitMqBundle\Consumer\Processor
 */
class BatchMessageProcessor extends BaseMessageProcessor implements MessageProcessorInterface {

    /** @var AMQPMessage[] */
    protected $messages;
    /** @var  int */
    protected $batchSize;

    /**
     * @param AMQPMessage $amqpMessage
     * @internal param $callback
     * @return mixed|void
     */
    public function processMessage(AMQPMessage $amqpMessage) {
        $this->messages[] = $amqpMessage;
        if (count($this->messages) >= $this->batchSize) {
            $this->processMessagesInBatch();
        }
    }

    /**
     *
     */
    protected function processMessagesInBatch() {
        $messages = array_slice($this->messages, 0, $this->batchSize);
        $this->messages = array_slice($this->messages, $this->batchSize);
        $processFlagOrFlags = $this->callConsumerCallback($messages);
        $this->ackOrNackMessage($messages, $processFlagOrFlags);
        $this->consumer->incrementConsumed(count($messages));
    }

    /**
     * @param AMQPMessage[] $amqpMessages
     * @param int|int[] $processFlagOrFlags
     */
    protected function ackOrNackMessage($amqpMessages, $processFlagOrFlags) {
        foreach ($amqpMessages as $index => $amqpMessage) {
            $processFlag = is_array($processFlagOrFlags) && isset($processFlagOrFlags[$index]) ? $processFlagOrFlags[$index] : $processFlagOrFlags;
            $this->consumer->ackOrNackMessage($amqpMessage, $processFlag);
        }
    }
}