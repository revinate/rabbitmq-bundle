<?php

namespace Revinate\RabbitMqBundle\Consumer\Processor;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Consumer;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Exceptions\InvalidCountOfResponseStatusesException;
use Revinate\RabbitMqBundle\Exceptions\RejectDropException;
use Revinate\RabbitMqBundle\Exceptions\RejectDropStopWithErrorException;
use Revinate\RabbitMqBundle\Exceptions\RejectDropWithErrorException;
use Revinate\RabbitMqBundle\Exceptions\RejectDropStopException;
use Revinate\RabbitMqBundle\Exceptions\RejectRepublishException;
use Revinate\RabbitMqBundle\Exceptions\RejectRequeueException;
use Revinate\RabbitMqBundle\Exceptions\RejectRequeueStopException;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\Message\Message;
use Revinate\RabbitMqBundle\Queue\Queue;

/**
 * Class BaseMessageProcessor
 * @package Revinate\RabbitMqBundle\Consumer\Processor
 */
abstract class BaseMessageProcessor {

    /** @var Consumer  */
    protected $consumer;

    /** @var  Queue */
    protected $queue;

    /** max retries to ack/nack */
    const MAX_RETRY_COUNT = 100;

    /**
     * @param Consumer $consumer
     */
    public function __construct(Consumer $consumer) {
        $this->consumer = $consumer;
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
            $message->setQueue($this->getQueue());
            $messages[] = $message;
        }
        $firstMessage = $messages[0];
        $exception = null;
        try {
            $this->setCallbackContainer();
            $messageParam = $this->consumer->isBatchConsumer() ? $messages : $firstMessage;
            $queue = $firstMessage->getQueue();
            // In case of BatchConsumer, $processFlag must be an array
            $processFlag = call_user_func_array($this->consumer->getCallback($queue), array($messageParam));
            foreach ($messages as $message) {
                $message->setProcessedAt(new \DateTime('now'));
            }
        } catch (RejectRequeueException $e) {
            // error_log("Event Requeued due to processing error: " . $e->getMessage());
            $processFlag = DeliveryResponse::MSG_REJECT_REQUEUE;
            $exception = $e;
        } catch (RejectRequeueStopException $e) {
            // error_log("Event Requeued due to processing error: " . $e->getMessage() . ", quiting");
            $processFlag = DeliveryResponse::MSG_REJECT_REQUEUE_STOP;
            $exception = $e;
        } catch (RejectDropStopException $e) {
            $processFlag = DeliveryResponse::MSG_REJECT_DROP_STOP;
            $exception = $e;
        } catch (RejectDropException $e) {
            // error_log("Event Dropped due to processing error: " . $e->getMessage());
            $processFlag = DeliveryResponse::MSG_REJECT;
            $exception = $e;
        } catch (RejectDropWithErrorException $e) {
            $processFlag = DeliveryResponse::MSG_REJECT_DROP_WITH_ERROR;
            $exception = $e;
        } catch (RejectDropStopWithErrorException $e) {
            $processFlag = DeliveryResponse::MSG_REJECT_DROP_STOP_WITH_ERROR;
            $exception = $e;
        }
        $processFlagOrFlags = $this->getSingleOrMultipleProcessFlags($processFlag, count($messages), !is_null($exception));
        // Ack or Nack Messages
        foreach ($messages as $index => $message) {
            $processFlag = is_array($processFlagOrFlags) && isset($processFlagOrFlags[$index]) ? $processFlagOrFlags[$index] : $processFlagOrFlags;
            $this->consumer->ackOrNackMessage($message, $processFlag, $exception);
            $retry = true;
            $retryCount = 0;
            while($retry) {
                try {
                    $this->consumer->ackOrNackMessage($message, $processFlag, $exception);
                    $retry = false;
                } catch (\Exception $e) {
                    ++$retryCount;
                    $retry = true;
                    if($retryCount > self::MAX_RETRY_COUNT){
                        $errorMsg = 'Enqueue Error while ACK: ' . $e->getMessage();
                        r_error_log(__METHOD__ . $errorMsg);
                        $retry = false;
                    }
                    usleep(25000);//0.025 seconds
                }
            }
        }
    }

    /**
     * Reject requeue messages
     * @param AMQPMessage[] $amqpMessages
     */
    public function rejectRequeueMessages($amqpMessages) {
        foreach ($amqpMessages as $amqpMessage) {
            $message = $this->consumer->getMessageFromAMQPMessage($amqpMessage);
            $this->consumer->ackOrNackMessage($message, DeliveryResponse::MSG_REJECT_REQUEUE);
        }
    }


    /**
     * @param int|int[] $processFlag
     * @param int $messagesCount
     * @param bool $isException
     * @throws \Revinate\RabbitMqBundle\Exceptions\InvalidCountOfResponseStatusesException
     * @return int|int[]
     */
    protected function getSingleOrMultipleProcessFlags($processFlag, $messagesCount, $isException) {
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
        throw new InvalidCountOfResponseStatusesException("If implementing BatchConsumerInterface, please return array of status flags of size equal to number of messages you received.");
    }

    /**
     * Set Container on the consumer
     */
    protected function setCallbackContainer() {
        $callback = $this->consumer->getSetContainerCallback($this->getQueue());
        if (! is_callable($callback)) {
            return;
        }
        call_user_func($callback, $this->consumer->getContainer());
    }

    /**
     * @param \Revinate\RabbitMqBundle\Queue\Queue $queue
     */
    public function setQueue($queue) {
        $this->queue = $queue;
    }

    /**
     * @return \Revinate\RabbitMqBundle\Queue\Queue
     */
    public function getQueue() {
        return $this->queue;
    }

}