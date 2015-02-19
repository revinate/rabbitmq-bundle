<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer;

use PhpAmqpLib\Message\AMQPMessage;

interface BatchConsumerInterface
{
    /**
     * @param AMQPMessage[] $messages
     * @return int
     */
    public function execute(Array $messages);
}
