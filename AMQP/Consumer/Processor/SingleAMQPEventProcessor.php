<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Consumer\AMQPEventConsumer;

class SingleAMQPEventProcessor extends BaseAMQPEventProcessor implements AMQPEventProcessorInterface {

    /** @var AMQPEventConsumer  */
    protected $consumer;

    /**
     * @param AMQPEventConsumer $consumer
     */
    public function __construct(AMQPEventConsumer $consumer) {
        $this->consumer = $consumer;
    }
    /**
     * @param AMQPMessage $message
     * @return mixed|void
     */
    public function processMessage(AMQPMessage $message) {
        $processFlag = $this->callConsumerCallback($message);
        $this->consumer->ackOrNackMessage($message, $processFlag);
        $this->consumer->incrementConsumed(1);
    }

    /**
     * @param AMQPMessage $message
     * @return int
     */
    public function callConsumerCallback($message) {
        $processFlag =  DeliveryResponse::MSG_ACK;
        $amqpEventMessage = $this->consumer->getAMQPEventMessage($message);
        $amqpEventMessage->setDequeuedAt(new \DateTime('now'));
        $fairnessAlgorithm = $this->consumer->getFairnessAlgorithm();
        try {
            if (!$this->consumer->isFairPublishMessage($amqpEventMessage) || $fairnessAlgorithm->isFairToProcess($amqpEventMessage)) {
                call_user_func($this->consumer->getCallback(), $amqpEventMessage);
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
        $fairnessAlgorithm->onMessageProcessed($amqpEventMessage);
        return $processFlag;
    }
}