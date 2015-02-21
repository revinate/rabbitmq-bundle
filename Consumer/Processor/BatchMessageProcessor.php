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
    protected $amqpMessages;
    /** @var  int */
    protected $batchSize;
    /** @var int timestamp in millisec when last batch was processed  */
    protected $processedBatchAt = 0;

    /**
     * @param AMQPMessage $amqpMessage
     * @internal param $callback
     * @return mixed|void
     */
    public function processMessage(AMQPMessage $amqpMessage) {
        $this->amqpMessages[] = $amqpMessage;
        $batchSize = $this->getBatchSizeToProcess();
        if ($batchSize > 0) {
            $amqpMessageBatch = array_slice($this->amqpMessages, 0, $batchSize);
            $this->amqpMessages = array_slice($this->amqpMessages, $batchSize);
            $this->processMessagesInBatch($amqpMessageBatch, $batchSize);
            $this->processedBatchAt = microtime(true) * 1000;
        }
    }

    /**
     * @param AMQPMessage[] $amqpMessages
     * @param int $batchSize
     */
    protected function processMessagesInBatch($amqpMessages, $batchSize) {
        $processFlagOrFlags = $this->callConsumerCallback($amqpMessages);
        $this->ackOrNackMessage($amqpMessages, $processFlagOrFlags);
        $this->consumer->incrementConsumed($batchSize);
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
    /**
     * Returns non zero if
     * - message count in buffer > batch size, or
     * - wait window is elapsed to wait for buffer to get filled up
     * @return int if batch should be processed, return > 0
     */
    protected function getBatchSizeToProcess() {
        if (count($this->amqpMessages) >= $this->batchSize) {
            return $this->batchSize;
        } else if (microtime(true) * 1000 - $this->processedBatchAt > $this->consumer->getBufferWait()) {
            return count($this->amqpMessages);
        }
        return 0;
    }
}