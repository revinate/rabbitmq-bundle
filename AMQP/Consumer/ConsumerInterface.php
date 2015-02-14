<?php

namespace Revinate\RabbitMqBundle\AMQP\Consumer;

use PhpAmqpLib\Message\AMQPMessage;

interface ConsumerInterface
{
    /**
     * @param AMQPMessage $msg
     * @return int
     */
    public function execute(AMQPMessage $msg);
}
