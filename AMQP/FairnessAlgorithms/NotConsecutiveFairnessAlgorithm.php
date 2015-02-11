<?php

namespace Revinate\RabbitMqBundle\AMQP\FairnessAlgorithms;

use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;

class NotConsecutiveFairnessAlgorithm implements FairnessAlgorithmInterface {

    /** @var string|null  */
    protected $fairnessKeyForLastMessage = null;

    /**
     * @param AMQPEventMessage $amqpEventMessage
     * @return bool
     */
    public function isFairToProcess(AMQPEventMessage $amqpEventMessage) {
        return is_null($this->fairnessKeyForLastMessage) || $amqpEventMessage->getFairnessKey() != $this->fairnessKeyForLastMessage;
    }

    /**
     * @param AMQPEventMessage $amqpEventMessage
     * @return mixed
     */
    public function onMessageProcessed(AMQPEventMessage $amqpEventMessage) {
        $fairnessKey = $amqpEventMessage->getFairnessKey();
        $this->fairnessKeyForLastMessage = $fairnessKey;
    }
}