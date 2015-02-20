<?php

namespace Revinate\RabbitMqBundle\Lib;

/**
 * Class DateHelper
 * @package Revinate\RabbitMqBundle\Lib
 */
class DateHelper {
    /**
     * @param $dateString
     * @return \DateTime|null
     */
    public static function convertDateToDateTime($dateString) {
        return $dateString ? new \DateTime($dateString) : null;
    }
}