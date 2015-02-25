<?php

namespace Revinate\RabbitMqBundle\Encoder;

/**
 * Interface DecoderInterface
 * @package Revinate\RabbitMqBundle\Encoder
 */
interface EncoderInterface {

    /**
     * @param mixed $value
     * @return mixed
     */
    public function encode($value);

}