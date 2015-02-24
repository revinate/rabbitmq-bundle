<?php

namespace Revinate\RabbitMqBundle\Consumer\Processor;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Consumer;

/**
 * Interface MessageProcessorInterface
 * @package Revinate\RabbitMqBundle\Consumer\Processor
 */
interface MessageProcessorInterface {

    /**
     * @param Consumer $consumer
     */
    public function __construct(Consumer $consumer);
    /**
     * @param AMQPMessage $amqpMessage
     */
    public function processMessage(AMQPMessage $amqpMessage);
}