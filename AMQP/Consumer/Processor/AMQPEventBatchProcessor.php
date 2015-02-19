<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Consumer\AMQPEventConsumer;

class AMQPEventBatchProcessor extends BaseAMQPEventProcessor implements AMQPEventProcessorInterface {

    const BATCH_SIZE = 10;

    /** @var AMQPEventConsumer  */
    protected $consumer;
    /** @var AMQPMessage[] */
    protected $messages;

    /**
     * @param AMQPEventConsumer $consumer
     */
    public function __construct(AMQPEventConsumer $consumer) {
        $this->consumer = $consumer;
    }

    /**
     * @param AMQPMessage $message
     * @internal param $callback
     * @return mixed|void
     */
    public function processMessage(AMQPMessage $message) {
        $this->messages[] = $message;
        if (count($this->messages) >= $this->consumer->getBatchSize()) {
            $this->processMessagesInBatch();
        }
    }

    /**
     *
     */
    protected function processMessagesInBatch() {
        $messages = array_slice($this->messages, 0, self::BATCH_SIZE);
        $this->messages = array_slice($this->messages, self::BATCH_SIZE);
        $processFlag = $this->callConsumerCallback($messages);
        $this->ackOrNackMessage($messages, $processFlag);
        $this->consumer->incrementConsumed(count($messages));
    }

    /**
     * @param AMQPMessage[] $messages
     * @return int
     */
    public function callConsumerCallback($messages) {
        $processFlag =  DeliveryResponse::MSG_ACK;
        $amqpEventMessages = [];
        foreach ($messages as $message) {
            $amqpEventMessage = $this->consumer->getAMQPEventMessage($message);
            $amqpEventMessage->setDequeuedAt(new \DateTime('now'));
            $amqpEventMessages[] = $amqpEventMessage;
        }
        $firstMessage = $amqpEventMessages[0];
        $fairnessAlgorithm = $this->consumer->getFairnessAlgorithm();
        try {
            if (!$this->consumer->isFairPublishMessage($firstMessage) || $fairnessAlgorithm->isFairToProcess($firstMessage)) {
                call_user_func($this->consumer->getCallback(), $amqpEventMessages);
                $amqpEventMessage->setProcessedAt(new \DateTime('now'));
            } else {
                error_log("Event Requeued due to unfairness. Key: " . $amqpEventMessage->getFairnessKey());
                $processFlag = DeliveryResponse::MSG_REJECT_REQUEUE;
            }
        } catch (RejectRequeueException $e) {
            error_log("Event Requeued due to processing error: " . $e->getMessage());
            $processFlag = DeliveryResponse::MSG_REJECT_REQUEUE;
        } catch (RejectDropException $e) {
            error_log("Event Dropped due to processing error: " . $e->getMessage());
            $processFlag = DeliveryResponse::MSG_REJECT;
        }
        $fairnessAlgorithm->onMessageProcessed($firstMessage);
        return $processFlag;
    }

    /**
     * @param AMQPMessage[] $messages
     * @param int $processFlag
     */
    protected function ackOrNackMessage($messages, $processFlag) {
        foreach ($messages as $message) {
            $this->consumer->ackOrNackMessage($message, $processFlag);
        }
    }
}