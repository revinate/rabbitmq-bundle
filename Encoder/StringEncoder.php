<?php

namespace Revinate\RabbitMqBundle\Encoder;

/**
 * Class StringEncoder
 * @package Revinate\RabbitMqBundle\Encoder
 */
class StringEncoder implements EncoderInterface {


    /**
     * @param string $value
     * @return string
     */
    public function encode($value)
    {
        return $value;
    }
}
