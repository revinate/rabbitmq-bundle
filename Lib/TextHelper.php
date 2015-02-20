<?php

namespace Revinate\RabbitMqBundle\Lib;

/**
 * Class TextHelper
 * @package Revinate\RabbitMqBundle\Lib
 */
class TextHelper {

    /**
     * Returns a random string of given length
     * @param int   $length     length of random string
     * @return string
     */
    public static function getRandomString($length) {
        $string = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($string), 0, $length);
    }
}