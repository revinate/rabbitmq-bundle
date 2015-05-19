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
use Revinate\RabbitMqBundle\Exceptions\NoQueuesConfiguredForConsumerException;
use Revinate\RabbitMqBundle\Exceptions\QueuesHavingMultipleConnectionsException;
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
    /** @var Queue[] */
    protected $queues;
    /** @var int  */
    protected $target = 1;
    /** @var array number consumed per queue  */
    protected $consumed = array();
    /** @var array callbacks for each queue */
    protected $callbacks;
    /** @var  array setContainer callbacks for each queue */
    protected $setContainerCallbacks;
    /** @var int  */
    protected $idleTimeout = 0;
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
    /** @var array  */
    protected $qosOptions = array();
    /** @var  array */
    protected $stoppedQueue = array();

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param $name
     * @param Queue[] $queues
     * @throws \BadFunctionCallException
     * @internal param \Revinate\RabbitMqBundle\Queue\Queue[] $queue
     */
    public function __construct(ContainerInterface $container = null, $name, Array $queues) {
        $this->container = $container;
        $this->name = $name;
        // Use first queues connection as the default connection
        $this->connection = $queues[0]->getConnection();
        $this->channel = $queues[0]->getChannel();
        $this->queues = $queues;

        $this->validateQueues();
        $this->loadSignalHandlers();
    }

    /**
     * @throws \BadFunctionCallException
     */
    protected function loadSignalHandlers() {
        if (extension_loaded('pcntl')) {
            if (!function_exists('pcntl_signal')) {
                throw new \BadFunctionCallException("Function 'pcntl_signal' is referenced in the php.ini 'disable_functions' and can't be called.");
            }
            pcntl_signal(SIGTERM, array(&$this, 'stopAllConsumers'));
            pcntl_signal(SIGINT, array(&$this, 'stopAllConsumers'));
        }
    }

    /**
     * Ensure all consumers are using the same connection
     */
    protected function validateQueues() {
        $connection = $this->queues[0]->getConnection();
        foreach ($this->queues as $queue) {
            if ($connection !== $queue->getConnection()) {
                throw new QueuesHavingMultipleConnectionsException(__METHOD__ . " Can't read from given queues as they use different connections.");
            }
        }
    }

    /**
     * Consume the message
     * @param int $messageCount
     */
    public function consume($messageCount) {
        $this->setTarget($messageCount);
        foreach ($this->queues as $queue) {
            $messageProcessor = !$this->isBatchConsumer() ? new SingleMessageProcessor($this) : new BatchMessageProcessor($this);
            $messageProcessor->setQueue($queue);
            $this->getChannel()->basic_qos($this->qosOptions['prefetch_size'], $this->getPrefetchCount(), $this->qosOptions['global']);
            $this->getChannel()->basic_consume($queue->getName(), $this->getConsumerTag($queue), false, false, false, false, array($messageProcessor, 'processMessage'));
        }
        $this->waitForMessages();
    }

    /**
     * For batch consumers, assume prefetch count = target as doing anything else causes issues
     * where rabbitmq doesn't send messages until the previous prefetch size is acked.
     * The problem specially arises when prefetch size != batch size.
     * Example:
     * Assume batch size is 10 and prefetch Count is 10.
     * - In our implementation, batch size increases like 1,2,4,8,16 etc till the batch size. So
     *   in this example, batch size increases like 1,2,4,8,10,10,10...
     * - If prefetch count is 10, server sends over 10 messages to the client.
     * - When batch size is 1, 1 message is consumed and acked
     * - When batch size is 2, 2 messages are consumed and acked
     * - When batch size is 4, 4 messages are consumed and acked
     * - Till now total of 7 messages are consumed. Now batch size is 8 and 3 messages are waiting to be consumed.
     *   Since 3 < 8 (batch size), we do not flush the queue and server does not send any more messages since it
     *   has not received ack for all 10 messages (prefetch count = 10). Therefore, we are stuck in a deadlock
     *   and process quits after timeout. Note that, buffer_wait doesn't help here since for us to flush the buffer,
     *   we need to receive atleast 1 message.
     *
     * @return int
     */
    protected function getPrefetchCount() {
        $prefetchCount = $this->qosOptions['prefetch_count'];
        if ($this->isBatchConsumer() && $this->qosOptions['prefetch_count'] < $this->getTarget()) {
            $prefetchCount = $this->getTarget();
        }
        return $prefetchCount;
    }

    /**
     * Wait for messages and call the callback
     */
    protected function waitForMessages() {
        while (count($this->getChannel()->callbacks)) {
            $this->stopConsumerIfTargetReached();
            $this->getChannel()->wait(null, false, $this->getIdleTimeout());
        }
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
        foreach ($this->queues as $queue) {
            if ($this->getTarget() > 0 && $this->getConsumed($queue) >= $this->getTarget()) {
                $this->stopConsuming($queue);
            }
        }
    }

    /**
     * Signal Listener
     * @param int $signo
     */
    public function stopAllConsumers($signo) {
        foreach ($this->queues as $queue) {
            $this->stopConsuming($queue);
        }
    }

    /**
     * Stop Consuming
     * @param \Revinate\RabbitMqBundle\Queue\Queue $queue
     */
    public function stopConsuming(Queue $queue) {
        if (isset($this->stoppedQueue[$queue->getName()])) {
            return;
        }
        $this->getChannel()->basic_cancel($this->getConsumerTag($queue));
        $this->stoppedQueue[$queue->getName()] = true;
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
        if ($processFlag === DeliveryResponse::MSG_REJECT_REQUEUE || false === $processFlag) {
            // Reject and requeue message to RabbitMQ
            $channel->basic_reject($deliveryTag, true);
        } else if ($processFlag === DeliveryResponse::MSG_SINGLE_NACK_REQUEUE) {
            // NACK and requeue message to RabbitMQ (basic_nack is rabbitmq only, not an AMQP standard)
            $channel->basic_nack($deliveryTag, false, true);
        } else if ($processFlag === DeliveryResponse::MSG_REJECT) {
            // Reject and drop
            $channel->basic_reject($deliveryTag, false);
        } else {
            // Remove message from queue only if callback return not false
            $channel->basic_ack($deliveryTag);
        }
        $this->incrementConsumed($message->getQueue(), 1);
    }

    /**
     * Sets the qos settings for the current channel
     * Consider that prefetchSize and global do not work with rabbitMQ version <= 8.0
     *
     * @param array $qosOptions
     */
    public function setQosOptions($qosOptions) {
        $this->qosOptions = $qosOptions;
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
     * @param $callbacks
     */
    public function setCallbacks($callbacks) {
        foreach ($this->queues as $index => $queue) {
            $this->callbacks[$queue->getName()] = $callbacks[$index];
        }
    }

    /**
     * @param \Revinate\RabbitMqBundle\Queue\Queue $queue
     * @throws \Revinate\RabbitMqBundle\Exceptions\NoConsumerCallbackForMessageException
     * @return null
     */
    public function getCallback(Queue $queue) {
        if (! isset($this->callbacks[$queue->getName()])) {
            throw new NoConsumerCallbackForMessageException("No callback specified for consumer queue: " . $this->getName() . ": " . $queue->getName());
        }
        return $this->callbacks[$queue->getName()];
    }

    /**
     * @param mixed $setContainerCallbacks
     */
    public function setSetContainerCallbacks($setContainerCallbacks)
    {
        foreach ($this->queues as $index => $queue) {
            $this->setContainerCallbacks[$queue->getName()] = $setContainerCallbacks[$index];
        }
    }

    /**
     * @param \Revinate\RabbitMqBundle\Queue\Queue $queue
     * @return mixed
     */
    public function getSetContainerCallback(Queue $queue)
    {
        return $this->setContainerCallbacks[$queue->getName()];
    }

    /**
     * @param Queue $queue
     * @return string
     */
    public function getConsumerTag(Queue $queue)
    {
        return sprintf("PHPPROCESS_%s_%s_%s", gethostname(), getmypid(), $queue->getName());
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
     * @param \Revinate\RabbitMqBundle\Queue\Queue $queue
     * @param int $by
     * @internal param $
     */
    public function incrementConsumed(Queue $queue, $by = 1) {
        if (! isset($this->consumed[$queue->getName()])) {
            $this->consumed[$queue->getName()] = 0;
        }
        $this->consumed[$queue->getName()] = $this->consumed[$queue->getName()] + $by;
    }

    /**
     * @param \Revinate\RabbitMqBundle\Queue\Queue $queue
     * @return int
     */
    public function getConsumed(Queue $queue)
    {
        return isset($this->consumed[$queue->getName()]) ? $this->consumed[$queue->getName()] : 0;
    }

    /**
     * @param Queue $queue
     * @return int
     */
    public function getToConsume(Queue $queue) {
        return $this->getTarget() - $this->getConsumed($queue);
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