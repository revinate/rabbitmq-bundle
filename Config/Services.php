<?php

namespace Revinate\RabbitMqBundle\Config;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\Queue\Queue;

/**
 * Class Services
 * @package Revinate\RabbitMqBundle\Config
 */
class Services {

    /** @var  Exchange[] */
    protected $exchanges = array();
    /** @var  Queue[] */
    protected $queues = array();

    /**
     * @param Exchange $exchange
     */
    public function addExchange($exchange)
    {
        $this->exchanges[] = $exchange;
    }

    /**
     * @return Exchange[]
     */
    public function getExchanges()
    {
        return $this->exchanges;
    }

    /**
     * @param Queue $queue
     */
    public function addQueue($queue)
    {
        $this->queues[] = $queue;
    }

    /**
     * @return Queue[]
     */
    public function getQueues()
    {
        return $this->queues;
    }

}