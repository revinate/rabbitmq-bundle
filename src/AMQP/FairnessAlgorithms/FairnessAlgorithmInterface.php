<?php

namespace Revinate\RabbitMqBundle\AMQP\FairnessAlgorithms;

use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;

interface FairnessAlgorithmInterface {
    /**
     * @param \Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage $amqpEventMessage
     * @return bool
     */
    public function isFairToProcess(AMQPEventMessage $amqpEventMessage);

    /**
     * @param AMQPEventMessage $amqpEventMessage
     * @return mixed
     */
    public function onMessageProcessed(AMQPEventMessage $amqpEventMessage);
}