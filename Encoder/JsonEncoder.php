<?php

namespace Revinate\RabbitMqBundle\Encoder;

/**
 * Class JsonDecoder
 * @package Revinate\RabbitMqBundle\Encoder
 */
class JsonEncoder implements EncoderInterface {


    /**
     * @param string $value
     * @return string
     */
    public function encode($value)
    {
        return json_encode($value);
    }
}
