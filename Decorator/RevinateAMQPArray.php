<?php

namespace Revinate\RabbitMqBundle\Decorator;

class RevinateAMQPArray extends AMQPArrayDecorator {

    public function encodeValuePublic($val) {
        return $this->encodeValue($val);
    }
}