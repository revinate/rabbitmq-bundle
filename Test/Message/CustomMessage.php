<?php

namespace Revinate\RabbitMqBundle\Test\Message;

use Revinate\RabbitMqBundle\Message\Message;

class CustomMessage extends Message {

    /**
     * @param $key
     * @param $value
     */
    public function setCustomHeader($key, $value) {
        $this->addHeader($key, $value);
    }
}