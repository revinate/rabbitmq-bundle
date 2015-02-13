<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Consumer\BaseAMQPEventConsumer;

interface AMQPEventProcessorInterface {

    /**
     * @param BaseAMQPEventConsumer $baseConsumer
     */
    public function __construct(BaseAMQPEventConsumer $baseConsumer);
    /**
     * @param AMQPMessage $message
     * @return mixed
     */
    public function processMessage(AMQPMessage $message);
}