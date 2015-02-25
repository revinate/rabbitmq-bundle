<?php

namespace Revinate\RabbitMqBundle\Decoder;

/**
 * Class SerializeDecoder
 * @package Revinate\RabbitMqBundle\Encoder
 */
class SerializeDecoder implements DecoderInterface {

    /**
     * @param string $value
     * @return string
     */
    public function decode($value)
    {
        return unserialize($value, true);
    }
}
