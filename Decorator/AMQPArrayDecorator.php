<?php

namespace Revinate\RabbitMqBundle\Decorator;

use PhpAmqpLib\Exception;
use PhpAmqpLib\Wire\AMQPArray;

abstract class AMQPArrayDecorator extends AMQPArray {
    /**
     * A public interface for encodeValue()
     *
     * @param mixed $val
     * @return mixed
     * @throws Exception\AMQPOutOfBoundsException
     */
    abstract public function encodeValuePublic($val);
}