<?php

namespace Revinate\RabbitMqBundle\Consumer;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Revinate\RabbitMqBundle\Consumer\Processor\MessageProcessorInterface;
use Revinate\RabbitMqBundle\Consumer\Processor\BatchMessageProcessor;
use Revinate\RabbitMqBundle\Consumer\Processor\SingleMessageProcessor;
use Revinate\RabbitMqBundle\Decoder\DecoderInterface;
use Revinate\RabbitMqBundle\Exceptions\NoConsumerCallbackForMessageException;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\Producer\BaseProducer;
use Revinate\RabbitMqBundle\Queue\Queue;
use Revinate\RabbitMqBundle\Message\Message;
use Revinate\RabbitMqBundle\FairnessAlgorithms\FairnessAlgorithmInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Consumer
 * @package Revinate\RabbitMqBundle\Consumer
 */
class Consumer {
    /** @var \Symfony\Component\DependencyInjection\ContainerInterface  */
    protected $container;
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
    protected $callback;
    /** @var   */
    protected $setContainerCallback;
    /** @var int  */
    protected $idleTimeout = 0;
    /** @var  string */
    protected $consumerTag;
    /** @var int  */
    protected $batchSize = null;
    /** @var  int If using batchSize, wait for these many ms before flushing buffer */
    protected $bufferWait;
    /** @var FairnessAlgorithmInterface */
    protected $fairnessAlgorithm = null;
    /** @var string  */
    protected $messageClass = null;
    /** @var  DecoderInterface */
    protected $decoder;

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param $name
     * @param \Revinate\RabbitMqBundle\Queue\Queue $queue
     */
    public function __construct(ContainerInterface $container = null, $name, Queue $queue) {
        $this->container = $container;
        $this->name = $name;
        $this->connection = $queue->getExchange()->getConnection();
        $this->queue = $queue;
        $this->channel = $this->connection->channel();
        $this->consumerTag = sprintf("PHPPROCESS_%s_%s", gethostname(), getmypid());

        if (extension_loaded('pcntl')) {
            if (!function_exists('pcntl_signal')) {
                throw new \BadFunctionCallException("Function 'pcntl_signal' is referenced in the php.ini 'disable_functions' and can't be called.");
            }
            pcntl_signal(SIGTERM, array(&$this, 'stopConsuming'));
            pcntl_signal(SIGINT, array(&$this, 'stopConsuming'));
        }
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
        $this->setTarget($messageCount);
        $this->messageProcessor = !$this->isBatchConsumer() ? new SingleMessageProcessor($this) : new BatchMessageProcessor($this);
        $this->setupConsumer();
        while (count($this->getChannel()->callbacks)) {
            $this->stopConsumerIfTargetReached();
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
     * Stop Consuming
     */
    public function stopConsuming() {
        $this->getChannel()->basic_cancel($this->getConsumerTag());
    }


    /**
     * May be stop the consumer
     * @throws \BadFunctionCallException
     */
    public function stopConsumerIfTargetReached() {
        if (extension_loaded('pcntl') && (defined('AMQP_WITHOUT_SIGNALS') ? !AMQP_WITHOUT_SIGNALS : true)) {
            if (!function_exists('pcntl_signal_dispatch')) {
                throw new \BadFunctionCallException("Function 'pcntl_signal_dispatch' is referenced in the php.ini 'disable_functions' and can't be called.");
            }
            pcntl_signal_dispatch();
        }
        if ($this->getTarget() > 0 && $this->getConsumed() >= $this->getTarget()) {
            $this->stopConsuming();
        }
    }

    /**
     *
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @param $processFlag
     */
    public function ackOrNackMessage(Message $message, $processFlag) {
        $amqpMessage = $message->getAmqpMessage();
        $channel = $amqpMessage->delivery_info['channel'];
        $deliveryTag = $amqpMessage->delivery_info['delivery_tag'];
        /**
         * When Container is not available like in Symfony 1.2, just reject requeue message intead of republish
         */
        if (is_null($this->container) && $processFlag === DeliveryResponse::MSG_REJECT_REPUBLISH) {
            $processFlag = DeliveryResponse::MSG_REJECT_REQUEUE;
        }
        if ($processFlag === DeliveryResponse::MSG_REJECT_REQUEUE || false === $processFlag) {
            // Reject and requeue message to RabbitMQ
            $channel->basic_reject($deliveryTag, true);
        } else if ($processFlag === DeliveryResponse::MSG_SINGLE_NACK_REQUEUE) {
            // NACK and requeue message to RabbitMQ (basic_nack is rabbitmq only, not an AMQP standard)
            $channel->basic_nack($deliveryTag, false, true);
        } else if ($processFlag === DeliveryResponse::MSG_REJECT) {
            // Reject and drop
            $channel->basic_reject($deliveryTag, false);
        } else if ($processFlag === DeliveryResponse::MSG_REJECT_REPUBLISH) {
            /** @var BaseProducer $baseProducer */
            $baseProducer = $this->container->get('revinate.rabbit_mq.base_producer');
            /** @var Exchange $exchange */
            $exchange = $this->container->get('revinate.rabbit_mq.exchange' . $message->getExchangeName());
            $baseProducer->setExchange($exchange);
            $baseProducer->rePublish($message);
        } else {
            // Remove message from queue only if callback return not false
            $channel->basic_ack($deliveryTag);
        }
        $this->incrementConsumed(1);
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
        return !is_null($this->getBatchSize());
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
     * @param mixed $setContainerCallback
     */
    public function setSetContainerCallback($setContainerCallback)
    {
        $this->setContainerCallback = $setContainerCallback;
    }

    /**
     * @return mixed
     */
    public function getSetContainerCallback()
    {
        return $this->setContainerCallback;
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
        /** @var AMQPTable|Array $headers */
        $headers = $properties['application_headers'];
        $headers = $headers instanceof AMQPTable ? $headers->getNativeData() : $headers;
        $decodedData = $this->decoder->decode($amqpMessage->body);
        if ($this->getMessageClass()) {
            $messageClass = $this->getMessageClass();
            $message = new $messageClass($decodedData, $routingKey, $headers);
        } else {
            $message = new Message($decodedData, $routingKey, $headers);
        }
        $message->setAmqpMessage($amqpMessage);
        return $message;
    }

    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param int $bufferWait
     */
    public function setBufferWait($bufferWait)
    {
        $this->bufferWait = $bufferWait;
    }

    /**
     * @return int
     */
    public function getBufferWait()
    {
        return $this->bufferWait;
    }

    /**
     * @param \Revinate\RabbitMqBundle\Decoder\DecoderInterface $decoder
     */
    public function setDecoder($decoder)
    {
        $this->decoder = $decoder;
    }

    /**
     * @return \Revinate\RabbitMqBundle\Decoder\DecoderInterface
     */
    public function getDecoder()
    {
        return $this->decoder;
    }
}