<?php
namespace Revinate\RabbitMqBundle\Producer;

use Revinate\RabbitMqBundle\Exchange\Exchange;

/**
 * Class BaseProducer
 * @package Revinate\RabbitMqBundle
 */
class BaseProducer extends Producer {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(null, null);
    }

    /**
     * @param Exchange $exchange
     */
    public function setExchange(Exchange $exchange) {
        $this->exchange = $exchange;
    }
}