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
     * Destructor
     */
    public function __destruct() {
        if (count($this->amqpMessages) > 0) {
            $this->callConsumerCallback($this->amqpMessages);
        }
    }

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
            $this->callConsumerCallback($amqpMessageBatch);
            $this->processedBatchAt = microtime(true) * 1000;
            $this->amqpMessages = array_slice($this->amqpMessages, $batchSize);
        }
        $this->consumer->stopConsumerIfTargetReached();
    }

    /**
     * Returns non zero if
     * - message count in buffer > batch size, or
     * - wait window is elapsed to wait for buffer to get filled up
     * @return int if batch should be processed, return > 0
     */
    protected function getBatchSizeToProcess() {
        $consumerBatchSize = $this->consumer->getBatchSize();
        if (count($this->amqpMessages) >= $consumerBatchSize) {
            return $consumerBatchSize;
        } else if (microtime(true) * 1000 - $this->processedBatchAt > $this->consumer->getBufferWait()) {
            return count($this->amqpMessages);
        }
        return 0;
    }
}