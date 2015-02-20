<?php

namespace Revinate\RabbitMqBundle\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Consumer;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;

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
        $processFlag = $this->callConsumerCallback($amqpMessage);
        $this->consumer->ackOrNackMessage($amqpMessage, $processFlag);
        $this->consumer->incrementConsumed(1);
    }

    /**
     * @param AMQPMessage $amqpMessage
     * @return int
     */
    public function callConsumerCallback($amqpMessage) {
        $processFlag =  DeliveryResponse::MSG_ACK;
        $message = $this->consumer->getMessageFromAMQPMessage($amqpMessage);
        $message->setDequeuedAt(new \DateTime('now'));
        $fairnessAlgorithm = $this->consumer->getFairnessAlgorithm();
        $isFairPublishMessage = $this->consumer->isFairPublishMessage($message);
        try {
            if (!$isFairPublishMessage || $fairnessAlgorithm->isFairToProcess($message)) {
                call_user_func($this->consumer->getCallback(), $message);
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
            $fairnessAlgorithm->onMessageProcessed($message);
        }
        return $processFlag;
    }
}