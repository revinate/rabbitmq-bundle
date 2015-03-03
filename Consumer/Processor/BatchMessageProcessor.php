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
    /** @var  int Batch Size: Starts from 1 and increments to $consumer->batchSize in steps */
    protected $batchSize = 1;
    /** @var int timestamp in millisec when last batch was processed  */
    protected $processedBatchAt;

    /**
     * @param Consumer $consumer
     */
    public function __construct(Consumer $consumer) {
        $this->processedBatchAt = microtime(true) * 1000;
        parent::__construct($consumer);
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
            $this->updateDynamicBatchSize();
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
        $return = 0;
        if (count($this->amqpMessages) >= $this->batchSize) {
            $return = $this->batchSize;
        } else if (microtime(true) * 1000 - $this->processedBatchAt > $this->consumer->getBufferWait()) {
            $return = count($this->amqpMessages);
        }
        return $return;
    }

    /**
     * Update dynamic batch size.
     * The batch size starts from One and increments to $this->consumer->getBatchSize() in steps.
     * This is to ensure that when queue size is less than batchSize, we still process messages
     * @TODO: Think of a better way to handle this case
     */
    protected function updateDynamicBatchSize() {
        $maxBatchSize = $this->consumer->getBatchSize();
        $this->batchSize = $this->batchSize * 2 > $maxBatchSize ? $maxBatchSize : $this->batchSize * 2;
    }
}