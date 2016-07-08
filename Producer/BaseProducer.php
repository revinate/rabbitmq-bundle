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
        $this->connection = $this->getExchange()->getConnection();
        $this->channelId = $this->connection->get_free_channel_id();
    }
}