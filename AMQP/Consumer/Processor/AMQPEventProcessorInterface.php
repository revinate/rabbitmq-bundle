<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Consumer\AMQPEventConsumer;

interface AMQPEventProcessorInterface {

    /**
     * @param AMQPEventConsumer $baseConsumer
     */
    public function __construct(AMQPEventConsumer $baseConsumer);
    /**
     * @param AMQPMessage $message
     */
    public function processMessage(AMQPMessage $message);
}