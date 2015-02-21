<?php

namespace Revinate\RabbitMqBundle\Consumer\Processor;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Consumer;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Exceptions\InvalidCountOfResponseStatusesException;
use Revinate\RabbitMqBundle\Exceptions\RejectDropException;
use Revinate\RabbitMqBundle\Exceptions\RejectRequeueException;
use Revinate\RabbitMqBundle\Message\Message;

/**
 * Class BaseMessageProcessor
 * @package Revinate\RabbitMqBundle\Consumer\Processor
 */
abstract class BaseMessageProcessor {

    /** @var Consumer  */
    protected $consumer;

    /**
     * @param Consumer $consumer
     */
    public function __construct(Consumer $consumer) {
        $this->consumer = $consumer;
        $this->batchSize = $this->consumer->getBatchSize();
    }

    /**
     * @param AMQPMessage[] $amqpMessages
     * @return int
     */
    public function callConsumerCallback($amqpMessages) {
        /** @var Message[] $messages */
        $messages = array();
        foreach ($amqpMessages as $amqpMessage) {
            $message = $this->consumer->getMessageFromAMQPMessage($amqpMessage);
            $message->setDequeuedAt(new \DateTime('now'));
            $message->setConsumer($this->consumer);
            $message->setAmqpMessage($amqpMessage);
            $messages[] = $message;
        }
        $firstMessage = $messages[0];
        $fairnessAlgorithm = $this->consumer->getFairnessAlgorithm();
        $isFairPublishMessage = $this->consumer->isFairPublishMessage($firstMessage);
        try {
            $isException = false;
            if (!$isFairPublishMessage || $fairnessAlgorithm->isFairToProcess($firstMessage)) {
                call_user_func($this->consumer->getSetContainerCallback(), $this->consumer->getContainer());
                $messageParam = $this->consumer->isBatchConsumer() ? $messages : $firstMessage;
                // In case of BatchConsumer, $processFlag must be an array
                $processFlag = call_user_func_array($this->consumer->getCallback(), array($messageParam));
                foreach ($messages as $message) {
                    $message->setProcessedAt(new \DateTime('now'));
                }
            } else {
                error_log("Event Requeued due to unfairness. Key: " . $firstMessage->getFairnessKey());
                $processFlag = DeliveryResponse::MSG_REJECT_REQUEUE;
            }
        } catch (RejectRequeueException $e) {
            error_log("Event Requeued due to processing error: " . $e->getMessage());
            $processFlag = DeliveryResponse::MSG_REJECT_REQUEUE;
            $isException = true;
        } catch (RejectDropException $e) {
            error_log("Event Dropped due to processing error: " . $e->getMessage());
            $processFlag = DeliveryResponse::MSG_REJECT;
            $isException = true;
        }
        if ($isFairPublishMessage) {
            $fairnessAlgorithm->onMessageProcessed($firstMessage);
        }
        return $this->returnSingleOrMultipleProcessFlags($processFlag, count($messages), $isException);
    }

    /**
     * @param int|int[] $processFlag
     * @param int $messagesCount
     * @param bool $isException
     * @throws \Revinate\RabbitMqBundle\Exceptions\InvalidCountOfResponseStatusesException
     * @return int|int[]
     */
    protected function returnSingleOrMultipleProcessFlags($processFlag, $messagesCount, $isException) {
        $isArray = is_array($processFlag);
        $processFlags = $isArray ? $processFlag : array($processFlag);
        if (!$this->consumer->isBatchConsumer()) {
            return $processFlags[0];
        }
        if ($isException) {
            $processFlags = array();
            for ($i = 0; $i < $messagesCount; $i++) {
                $processFlags[$i] = $processFlag;
            }
        }
        if (count($processFlags) == $messagesCount) {
            return $processFlags;
        }
        throw new InvalidCountOfResponseStatusesException("If implementing BatchConsumerInterface, please return array of status flags of size equal to your batch size.");
    }
}