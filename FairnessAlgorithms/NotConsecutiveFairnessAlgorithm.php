<?php

namespace Revinate\RabbitMqBundle\FairnessAlgorithms;

use Revinate\RabbitMqBundle\Message\Message;

class NotConsecutiveFairnessAlgorithm implements FairnessAlgorithmInterface {

    /** @var string|null  */
    protected $fairnessKeyForLastMessage = null;

    /**
     * @param Message $message
     * @return bool
     */
    public function isFairToProcess(Message $message) {
        return is_null($this->fairnessKeyForLastMessage) || $message->getFairnessKey() != $this->fairnessKeyForLastMessage;
    }

    /**
     * @param Message $message
     * @return mixed
     */
    public function onMessageProcessed(Message $message) {
        $fairnessKey = $message->getFairnessKey();
        $this->fairnessKeyForLastMessage = $fairnessKey;
    }
}