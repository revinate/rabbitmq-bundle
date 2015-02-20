<?php

namespace Revinate\RabbitMqBundle\FairnessAlgorithms;

use Revinate\RabbitMqBundle\Message\Message;

interface FairnessAlgorithmInterface {
    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return bool
     */
    public function isFairToProcess(Message $message);

    /**
     * @param Message $message
     * @return mixed
     */
    public function onMessageProcessed(Message $message);
}