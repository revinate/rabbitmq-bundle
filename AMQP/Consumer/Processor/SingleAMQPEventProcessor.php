<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Consumer\BaseAMQPEventConsumer;
use Revinate\RabbitMqBundle\AMQP\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\AMQP\Exceptions\NoConsumerCallbackForMessageException;

class SingleAMQPEventProcessor implements AMQPEventProcessorInterface {

    /** @var BaseAMQPEventConsumer  */
    protected $consumer;

    /**
     * @param BaseAMQPEventConsumer $consumer
     */
    public function __construct(BaseAMQPEventConsumer $consumer) {
        $this->consumer = $consumer;
    }

    /**
     * @param AMQPMessage $message
     * @throws \Revinate\RabbitMqBundle\AMQP\Exceptions\NoConsumerCallbackForMessageException
     * @return mixed|void
     */
    public function processMessage(AMQPMessage $message) {
        $processFlag = call_user_func($this->consumer->getCallback(), $message);
        $this->confirmOrRejectDelivery($message, $processFlag);
        $this->consumer->incrementConsumed(1);
    }

    /**
     * @param AMQPMessage $message
     * @param $processFlag
     */
    protected function confirmOrRejectDelivery(AMQPMessage $message, $processFlag) {
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