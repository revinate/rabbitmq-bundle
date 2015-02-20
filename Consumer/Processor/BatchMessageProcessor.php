<?php

namespace Revinate\RabbitMqBundle\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Consumer;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;

class BatchMessageProcessor extends BaseMessageProcessor implements MessageProcessorInterface {

    /** @var Consumer  */
    protected $consumer;
    /** @var AMQPMessage[] */
    protected $messages;
    /** @var  int */
    protected $batchSize;

    /**
     * @param Consumer $consumer
     */
    public function __construct(Consumer $consumer) {
        $this->consumer = $consumer;
        $this->batchSize = $this->consumer->getBatchSize();
    }

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
        $processFlag = $this->callConsumerCallback($messages);
        $this->ackOrNackMessage($messages, $processFlag);
        $this->consumer->incrementConsumed(count($messages));
    }

    /**
     * @param AMQPMessage[] $amqpMessages
     * @return int
     */
    public function callConsumerCallback($amqpMessages) {
        $processFlag =  DeliveryResponse::MSG_ACK;
        $amqpEventMessages = array();
        foreach ($amqpMessages as $amqpMessage) {
            $message = $this->consumer->getMessageFromAMQPMessage($amqpMessage);
            $message->setDequeuedAt(new \DateTime('now'));
            $amqpEventMessages[] = $message;
        }
        $firstMessage = $amqpEventMessages[0];
        $fairnessAlgorithm = $this->consumer->getFairnessAlgorithm();
        $isFairPublishMessage = $this->consumer->isFairPublishMessage($message);
        try {
            if (!$isFairPublishMessage || $fairnessAlgorithm->isFairToProcess($firstMessage)) {
                call_user_func($this->consumer->getCallback(), $amqpEventMessages);
                $message->setProcessedAt(new \DateTime('now'));
            } else {
                error_log("Event Requeued due to unfairness. Key: " . $message->getFairnessKey());
                $processFlag = DeliveryResponse::MSG_REJECT_REQUEUE;
            }
        } catch (RejectRequeueException $e) {
            error_log("Event Requeued due to processing error: " . $e->getMessage());
            $processFlag = DeliveryResponse::MSG_REJECT_REQUEUE;
        } catch (RejectDropException $e) {
            error_log("Event Dropped due to processing error: " . $e->getMessage());
            $processFlag = DeliveryResponse::MSG_REJECT;
        }
        if ($isFairPublishMessage) {
            $fairnessAlgorithm->onMessageProcessed($firstMessage);
        }
        return $processFlag;
    }

    /**
     * @param AMQPMessage[] $amqpMessages
     * @param int $processFlag
     */
    protected function ackOrNackMessage($amqpMessages, $processFlag) {
        foreach ($amqpMessages as $amqpMessage) {
            $this->consumer->ackOrNackMessage($amqpMessage, $processFlag);
        }
    }
}