<?php

namespace Revinate\RabbitMqBundle\Decoder;

/**
 * Interface DecoderInterface
 * @package Revinate\RabbitMqBundle\Decoder
 */
interface DecoderInterface {

    /**
     * @param mixed $value
     * @return mixed
     */
    public function decode($value);
}