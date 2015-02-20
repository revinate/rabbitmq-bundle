<?php

namespace Revinate\RabbitMqBundle\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Consumer;

interface MessageProcessorInterface {

    /**
     * @param Consumer $baseConsumer
     */
    public function __construct(Consumer $baseConsumer);
    /**
     * @param AMQPMessage $amqpMessage
     */
    public function processMessage(AMQPMessage $amqpMessage);
}