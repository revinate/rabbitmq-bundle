<?php

namespace Revinate\RabbitMqBundle\AMQP;

use PhpAmqpLib\Message\AMQPMessage;

interface BatchedConsumerInterface
{
    /**
     * Flag for message ack
     */
    const MSG_ACK = 1;

    /**
     * Flag single for message nack and requeue
     */
    const MSG_SINGLE_NACK_REQUEUE = 2;

    /**
     * Flag for reject and requeue
     */
    const MSG_REJECT_REQUEUE = 0;

    /**
     * Flag for reject and drop
     */
    const MSG_REJECT = -1;

    /**
     * @return string
     */
    public function getName();

    /**
     * @param AMQPMessage[] $messages
     * @return int
     */
    public function execute(Array $messages);
}
