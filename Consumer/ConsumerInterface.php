<?php

namespace Revinate\RabbitMqBundle\Consumer;

use Revinate\RabbitMqBundle\Message\Message;

interface ConsumerInterface
{
    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return int
     */
    public function execute(Message $message);
}
