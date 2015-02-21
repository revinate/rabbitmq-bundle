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
        $message->setConsumer($this->consumer);
        $message->setAmqpMessage($amqpMessage);
        $fairnessAlgorithm = $this->consumer->getFairnessAlgorithm();
        $isFairPublishMessage = $this->consumer->isFairPublishMessage($message);
        try {
            if (!$isFairPublishMessage || $fairnessAlgorithm->isFairToProcess($message)) {
                call_user_func($this->consumer->getSetContainerCallback(), $this->consumer->getContainer());
                call_user_func_array($this->consumer->getCallback(), array($message));
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