<?php

namespace Revinate\RabbitMqBundle\Encoder;

/**
 * Class SerializeDecoder
 * @package Revinate\RabbitMqBundle\Encoder
 */
class SerializeEncoder implements EncoderInterface {

    /**
     * @param string $value
     * @return string
     */
    public function encode($value)
    {
        return serialize($value);
    }
}
