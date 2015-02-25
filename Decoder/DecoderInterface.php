<?php

namespace Revinate\RabbitMqBundle\Decoder;

/**
 * Interface DecoderInterface
 * @package Revinate\RabbitMqBundle\Encoder
 */
interface DecoderInterface {

    /**
     * @param mixed $value
     * @return mixed
     */
    public function decode($value);
}