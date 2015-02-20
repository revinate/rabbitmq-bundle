<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer;

interface BatchConsumerInterface
{
    /**
     * @param \Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage[] $messages
     * @return int
     */
    public function execute($messages);
}
