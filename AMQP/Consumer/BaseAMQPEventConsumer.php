<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Consumer\Processor\AMQPEventProcessorInterface;
use Revinate\RabbitMqBundle\AMQP\Consumer\Processor\SingleAMQPEventProcessor;
use Revinate\RabbitMqBundle\AMQP\Exceptions\NoConsumerCallbackForMessageException;
use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;
use Revinate\RabbitMqBundle\AMQP\Queue\AMQPQueue;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\Event;

class BaseAMQPEventConsumer {
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

    /**
     * @param $name
     * @param $queue
     */
    public function __construct($name, AMQPQueue $queue) {
        $this->name = $name;
        $this->connection = $queue->getExchange()->getConnection();
        $this->queue = $queue;
        $this->channel = $this->connection->channel();
        $this->messageProcessor = $this->getMessageProcessor();
        $this->consumerTag = sprintf("PHPPROCESS_%s_%s", gethostname(), getmypid());
    }

    /**
     * @throws NoConsumerCallbackForMessageException
     * @return SingleAMQPEventProcessor
     */
    public function getMessageProcessor() {
       return new SingleAMQPEventProcessor($this);
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
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
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
}