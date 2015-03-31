<?php

namespace Revinate\RabbitMqBundle\Decoder;

/**
 * Class JsonDecoder
 * @package Revinate\RabbitMqBundle\Decoder
 */
class JsonDecoder implements DecoderInterface {

    /**
     * @param string $value
     * @return string
     */
    public function decode($value)
    {
        return json_decode($value, true);
    }
}
