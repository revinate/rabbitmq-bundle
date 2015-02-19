<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Consumer\Processor\AMQPEventProcessorInterface;
use Revinate\RabbitMqBundle\AMQP\Consumer\Processor\AMQPEventBatchProcessor;
use Revinate\RabbitMqBundle\AMQP\Consumer\Processor\SingleAMQPEventProcessor;
use Revinate\RabbitMqBundle\AMQP\Exceptions\NoConsumerCallbackForMessageException;
use Revinate\RabbitMqBundle\AMQP\Queue\AMQPQueue;
use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;
use Revinate\RabbitMqBundle\AMQP\FairnessAlgorithms\FairnessAlgorithmInterface;

abstract class AMQPEventConsumer {
    /** @var string */
    protected $name;
    /** @var AMQPConnection */
    protected $connection;
    /** @var AMQPChannel  */
    protected $channel;
    /** @var AMQPQueue */
    protected $queue;
    /** @var AMQPEventProcessorInterface  */
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
     * @param \Revinate\RabbitMqBundle\AMQP\Queue\AMQPQueue $queue
     */
    public function __construct($name, AMQPQueue $queue) {
        $this->name = $name;
        $this->connection = $queue->getExchange()->getConnection();
        $this->queue = $queue;
        $this->channel = $this->connection->channel();
        $this->messageProcessor = $this->isBatchConsumer() ? new SingleAMQPEventProcessor($this) : new AMQPEventBatchProcessor($this);
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
     * @param AMQPMessage $message
     * @param $processFlag
     */
    public function ackOrNackMessage(AMQPMessage $message, $processFlag) {
        $channel = $message->delivery_info['channel'];
        $deliveryTag = $message->delivery_info['delivery_tag'];
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
     * @return AMQPQueue
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
     * @throws \Revinate\RabbitMqBundle\AMQP\Exceptions\NoConsumerCallbackForMessageException
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
     * @param \Revinate\RabbitMqBundle\AMQP\FairnessAlgorithms\FairnessAlgorithmInterface $fairnessAlgorithm
     */
    public function setFairnessAlgorithm($fairnessAlgorithm)
    {
        $this->fairnessAlgorithm = $fairnessAlgorithm;
    }

    /**
     * @return \Revinate\RabbitMqBundle\AMQP\FairnessAlgorithms\FairnessAlgorithmInterface
     */
    public function getFairnessAlgorithm()
    {
        return $this->fairnessAlgorithm;
    }

    /**
     * @param \Revinate\RabbitMqBundle\AMQP\Consumer\Processor\AMQPEventProcessorInterface $messageProcessor
     */
    public function setMessageProcessor($messageProcessor)
    {
        $this->messageProcessor = $messageProcessor;
    }

    /**
     * @return \Revinate\RabbitMqBundle\AMQP\Consumer\Processor\AMQPEventProcessorInterface
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
     * @param AMQPEventMessage $amqpEventMessage
     * @return bool
     */
    public function isFairPublishMessage(AMQPEventMessage $amqpEventMessage) {
        return !is_null($amqpEventMessage->getFairnessKey());
    }

    /**
     * @param AMQPMessage $message
     * @return AMQPEventMessage
     */
    public function getAMQPEventMessage(AMQPMessage $message) {
        $routingKey = $message->delivery_info['routing_key'];
        $properties = $message->get_properties();
        $headers = $properties['application_headers'];
        if ($this->getMessageClass()) {
            $messageClass = $this->getMessageClass();
            return new $messageClass(json_decode($message->body, true), $routingKey, $headers);
        } else {
            return new AMQPEventMessage(json_decode($message->body, true), $routingKey, $headers);
        }
    }
}