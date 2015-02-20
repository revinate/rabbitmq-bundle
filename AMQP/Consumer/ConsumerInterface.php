<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer;

use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;

interface ConsumerInterface
{
    /**
     * @param \Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage $message
     * @return int
     */
    public function execute(AMQPEventMessage $message);
}
