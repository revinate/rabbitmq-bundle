<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Consumer\BaseAMQPEventConsumer;
use Revinate\RabbitMqBundle\AMQP\Consumer\ConsumerInterface;

class BatchedAMQPEventProcessor implements AMQPEventProcessorInterface {

    const BATCH_SIZE = 10;

    /** @var BaseAMQPEventConsumer  */
    protected $consumer;
    /** @var AMQPMessage[] */
    protected $messages;


    /**
     * @param BaseAMQPEventConsumer $consumer
     */
    public function __construct(BaseAMQPEventConsumer $consumer) {
        $this->consumer = $consumer;
    }

    /**
     * @param AMQPMessage $message
     * @internal param $callback
     * @return mixed|void
     */
    public function processMessage(AMQPMessage $message) {
        $this->messages[] = $message;
        if (count($this->messages) >= self::BATCH_SIZE) {
            $this->processMessagesInBatch();
        }
    }

    /**
     *
     */
    protected function processMessagesInBatch() {
        $messages = array_slice($this->messages, 0, self::BATCH_SIZE);
        $this->messages = array_slice($this->messages, self::BATCH_SIZE);
        $processFlags = call_user_func($this->consumer->getCallback(), $messages);
        $this->confirmOrRejectDelivery($messages, $processFlags);
        $this->consumer->incrementConsumed(count($messages));
    }

    /**
     * @param AMQPMessage[] $messages
     * @param int[] $processFlags
     */
    protected function confirmOrRejectDelivery($messages, $processFlags) {
        foreach ($messages as $index => $message) {
            $processFlag = $processFlags[$index];
            if ($processFlag === ConsumerInterface::MSG_REJECT_REQUEUE || false === $processFlag) {
                // Reject and requeue message to RabbitMQ
                $message->delivery_info['channel']->basic_reject($message->delivery_info['delivery_tag'], true);
            } else if ($processFlag === ConsumerInterface::MSG_SINGLE_NACK_REQUEUE) {
                // NACK and requeue message to RabbitMQ
                $message->delivery_info['channel']->basic_nack($message->delivery_info['delivery_tag'], false, true);
            } else if ($processFlag === ConsumerInterface::MSG_REJECT) {
                // Reject and drop
                $message->delivery_info['channel']->basic_reject($message->delivery_info['delivery_tag'], false);
            } else {
                // Remove message from queue only if callback return not false
                $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
            }
        }
    }
}