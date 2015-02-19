<?php

namespace Revinate\RabbitMqBundle\AMQP\EventListeners\Types;

use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;

abstract class EventBaseAMQPListener {

    /** @var ContainerInterface */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param AMQPEventMessage $amqpMessage
     */
    abstract function process(AMQPEventMessage $amqpMessage);
}