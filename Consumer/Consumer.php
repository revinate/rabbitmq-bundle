<?php

namespace Revinate\RabbitMqBundle\Consumer;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Processor\MessageProcessorInterface;
use Revinate\RabbitMqBundle\Consumer\Processor\BatchMessageProcessor;
use Revinate\RabbitMqBundle\Consumer\Processor\SingleMessageProcessor;
use Revinate\RabbitMqBundle\Exceptions\NoConsumerCallbackForMessageException;
use Revinate\RabbitMqBundle\Queue\Queue;
use Revinate\RabbitMqBundle\Message\Message;
use Revinate\RabbitMqBundle\FairnessAlgorithms\FairnessAlgorithmInterface;

class Consumer {
    /** @var string */
    protected $name;
    /** @var AMQPConnection */
    protected $connection;
    /** @var AMQPChannel  */
    protected $channel;
    /** @var Queue */
    protected $queue;
    /** @var MessageProcessorInterface  */
    protected $messageProcessor;
    /** @var int  */
    protected $target = 1;
    /** @var int  */
    protected $consumed = 0;
    /** @var */
    protected $callback = null;
    /** @var int  */
    protected $idleTimeout = 0;
    /** @var  string */
    protected $consumerTag;
    /** @var int  */
    protected $batchSize = 1;
    /** @var FairnessAlgorithmInterface */
    protected $fairnessAlgorithm = null;
    /** @var string  */
    protected $messageClass = null;

    /**
     * @param $name
     * @param \Revinate\RabbitMqBundle\Queue\Queue $queue
     */
    public function __construct($name, Queue $queue) {
        $this->name = $name;
        $this->connection = $queue->getExchange()->getConnection();
        $this->queue = $queue;
        $this->channel = $this->connection->channel();
        $this->consumerTag = sprintf("PHPPROCESS_%s_%s", gethostname(), getmypid());
    }

    /**
     * Setup Consumer to Consume Messages
     */
    protected function setupConsumer() {
        $this->getChannel()->basic_consume($this->getQueue()->getName(), $this->getConsumerTag(), false, false, false, false, array($this->messageProcessor, 'processMessage'));
    }

    /**
     * Consume the message
     * @param int $messageCount
     */
    public function consume($messageCount) {
        $this->target = $messageCount;
        $this->messageProcessor = !$this->isBatchConsumer() ? new SingleMessageProcessor($this) : new BatchMessageProcessor($this);
        $this->setupConsumer();
        while (count($this->getChannel()->callbacks)) {
            $this->maybeStopConsumer();
            $this->getChannel()->wait(null, false, $this->getIdleTimeout());
        }
    }

    /**
     * Purge the queue
     */
    public function purge() {
        $this->getChannel()->queue_purge($this->getQueue()->getName(), true);
    }

    /**
     * @param int $messageCount
     */
    public function start($messageCount = 0) {
        $this->setTarget($messageCount);
        $this->setupConsumer();
        while (count($this->getChannel()->callbacks)) {
            $this->getChannel()->wait();
        }
    }

    /**
     * Stop Consuming
     */
    public function stopConsuming() {
        $this->getChannel()->basic_cancel($this->getConsumerTag());
    }


    /**
     * May be stop the consumer
     * @throws \BadFunctionCallException
     */
    protected function maybeStopConsumer() {
        if (extension_loaded('pcntl') && (defined('AMQP_WITHOUT_SIGNALS') ? !AMQP_WITHOUT_SIGNALS : true)) {
            if (!function_exists('pcntl_signal_dispatch')) {
                throw new \BadFunctionCallException("Function 'pcntl_signal_dispatch' is referenced in the php.ini 'disable_functions' and can't be called.");
            }
            pcntl_signal_dispatch();
        }

        if ($this->getConsumed() == $this->getTarget() && $this->getTarget() > 0) {
            $this->stopConsuming();
        } else {
            return;
        }
    }

    /**
     * @param AMQPMessage $amqpMessage
     * @param $processFlag
     */
    public function ackOrNackMessage(AMQPMessage $amqpMessage, $processFlag) {
        $channel = $amqpMessage->delivery_info['channel'];
        $deliveryTag = $amqpMessage->delivery_info['delivery_tag'];
        if ($processFlag === DeliveryResponse::MSG_REJECT_REQUEUE || false === $processFlag) {
            // Reject and requeue message to RabbitMQ
            $channel->basic_reject($deliveryTag, true);
        } else if ($processFlag === DeliveryResponse::MSG_SINGLE_NACK_REQUEUE) {
            // NACK and requeue message to RabbitMQ
            $channel->basic_nack($deliveryTag, false, true);
        } else if ($processFlag === DeliveryResponse::MSG_REJECT) {
            // Reject and drop
            $channel->basic_reject($deliveryTag, false);
        } else {
            // Remove message from queue only if callback return not false
            $channel->basic_ack($deliveryTag);
        }
    }

    /**
     * Sets the qos settings for the current channel
     * Consider that prefetchSize and global do not work with rabbitMQ version <= 8.0
     *
     * @param int  $prefetchSize
     * @param int  $prefetchCount
     * @param bool $global
     */
    public function setQosOptions($prefetchSize = 0, $prefetchCount = 0, $global = false) {
        $this->getChannel()->basic_qos($prefetchSize, $prefetchCount, $global);
    }

    /**
     * @param $idleTimeout
     */
    public function setIdleTimeout($idleTimeout) {
        $this->idleTimeout = $idleTimeout;
    }

    /**
     * @return mixed
     */
    public function getIdleTimeout() {
        return $this->idleTimeout;
    }

    /**
     * @return AMQPChannel
     */
    public function getChannel() {
        return $this->channel;
    }

    /**
     * @return \PhpAmqpLib\Connection\AMQPConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param int $batchSize
     */
    public function setBatchSize($batchSize)
    {
        $this->batchSize = $batchSize;
    }

    /**
     * @return int
     */
    public function getBatchSize()
    {
        return $this->batchSize;
    }

    /**
     * @return bool
     */
    public function isBatchConsumer() {
        return $this->getBatchSize() > 1;
    }

    /**
     * @param $callback
     */
    public function setCallback($callback) {
        $this->callback = $callback;
    }

    /**
     * @throws \Revinate\RabbitMqBundle\Exceptions\NoConsumerCallbackForMessageException
     * @return null
     */
    public function getCallback() {
        if (is_null($this->callback)) {
            throw new NoConsumerCallbackForMessageException("No callback specified for consumer: " . $this->getName());
        }
        return $this->callback;
    }

    /**
     * @param string $consumerTag
     */
    public function setConsumerTag($consumerTag)
    {
        $this->consumerTag = $consumerTag;
    }

    /**
     * @return string
     */
    public function getConsumerTag()
    {
        return $this->consumerTag;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Increment Consumed
     *
     */
    public function incrementConsumed($by = 1) {
        $this->consumed = $this->consumed + $by;
    }

    /**
     * @return int
     */
    public function getConsumed()
    {
        return $this->consumed;
    }

    /**
     * @return int
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param int $target
     */
    public function setTarget($target)
    {
        $this->target = $target;
    }

    /**
     * @param \Revinate\RabbitMqBundle\FairnessAlgorithms\FairnessAlgorithmInterface $fairnessAlgorithm
     */
    public function setFairnessAlgorithm($fairnessAlgorithm)
    {
        $this->fairnessAlgorithm = $fairnessAlgorithm;
    }

    /**
     * @return \Revinate\RabbitMqBundle\FairnessAlgorithms\FairnessAlgorithmInterface
     */
    public function getFairnessAlgorithm()
    {
        return $this->fairnessAlgorithm;
    }

    /**
     * @param \Revinate\RabbitMqBundle\Consumer\Processor\MessageProcessorInterface $messageProcessor
     */
    public function setMessageProcessor($messageProcessor)
    {
        $this->messageProcessor = $messageProcessor;
    }

    /**
     * @return \Revinate\RabbitMqBundle\Consumer\Processor\MessageProcessorInterface
     */
    public function getMessageProcessor()
    {
        return $this->messageProcessor;
    }

    /**
     * @param string $messageClass
     */
    public function setMessageClass($messageClass)
    {
        $this->messageClass = $messageClass;
    }

    /**
     * @return string
     */
    public function getMessageClass()
    {
        return $this->messageClass;
    }

    /**
     * @param Message $message
     * @return bool
     */
    public function isFairPublishMessage(Message $message) {
        return !is_null($message->getFairnessKey());
    }

    /**
     * @param AMQPMessage $amqpMessage
     * @return Message
     */
    public function getMessageFromAMQPMessage(AMQPMessage $amqpMessage) {
        $routingKey = $amqpMessage->delivery_info['routing_key'];
        $properties = $amqpMessage->get_properties();
        $headers = $properties['application_headers'];
        if ($this->getMessageClass()) {
            $messageClass = $this->getMessageClass();
            return new $messageClass(json_decode($amqpMessage->body, true), $routingKey, $headers);
        } else {
            return new Message(json_decode($amqpMessage->body, true), $routingKey, $headers);
        }
    }
}