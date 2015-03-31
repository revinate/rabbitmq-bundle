<?php

namespace Revinate\RabbitMqBundle\Decoder;

/**
 * Class StringDecoder
 * @package Revinate\RabbitMqBundle\Decoder
 */
class StringDecoder implements DecoderInterface {

    /**
     * @param string $value
     * @return string
     */
    public function decode($value)
    {
        return $value;
    }
}
