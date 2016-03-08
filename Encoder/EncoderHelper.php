<?php
namespace Revinate\RabbitMqBundle\Encoder;

use Revinate\RabbitMqBundle\Decoder\DecoderInterface;
use Revinate\RabbitMqBundle\Decoder\JsonDecoder;
use Revinate\RabbitMqBundle\Decoder\SerializeDecoder;
use Revinate\RabbitMqBundle\Decoder\StringDecoder;

class EncoderHelper {

    /**
     * @param DecoderInterface $decoder
     * @return EncoderInterface
     */
    public static function getEncoderFromDecoder(DecoderInterface $decoder) {
        if ($decoder instanceof JsonDecoder) {
            return new JsonEncoder();
        } elseif ($decoder instanceof StringDecoder) {
            return new StringEncoder();
        } elseif ($decoder instanceof SerializeDecoder) {
            return new SerializeEncoder();
        }
        return self::getDefaultEncoder();
    }

    /**
     * @return EncoderInterface
     */
    public static function getDefaultEncoder() {
        return new JsonEncoder();
    }
}