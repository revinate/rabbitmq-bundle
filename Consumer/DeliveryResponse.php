<?php

namespace Revinate\RabbitMqBundle\Consumer;

/**
 * Class DeliveryResponse
 * @package Revinate\RabbitMqBundle\Consumer
 */
class DeliveryResponse {

    /**
     * Flag for message ack
     */
    const MSG_ACK = 1;
    /**
     * Flag single for message nack and requeue
     * https://www.rabbitmq.com/nack.html
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
     * Flag for reject, requeue and stop
     */
    const MSG_REJECT_REQUEUE_STOP = 3;

    /**
     * Flag to reject drop with error, aka publish to deadletter exchange with error header
     */
    const MSG_REJECT_DROP_WITH_ERROR = 4;

    /**
     * Flag for reject, drop and stop
     */
    const MSG_REJECT_DROP_STOP = 5;
}