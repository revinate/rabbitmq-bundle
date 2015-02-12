<?php

namespace Revinate\RabbitMqBundle\AMQP\EventListeners\Types;

use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use Revinate\RabbitMqBundle\AMQP\Exceptions\RejectDropException;
use Revinate\RabbitMqBundle\AMQP\Exceptions\RejectRequeueException;
use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class EventBaseAMQPListener {

    /** @var ContainerInterface */
    protected $container;
    /** @var \Revinate\RabbitMqBundle\AMQP\AMQPEventProducer  */
    protected $producer;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->producer = $this->container->get('revinate.amqp_event.producer');
    }

    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @return \Revinate\RabbitMqBundle\AMQP\AMQPEventProducer
     */
    public function getProducer() {
        return $this->producer;
    }

    /**
     * @param AMQPEventMessage $amqpMessage
     */
    abstract function process(AMQPEventMessage $amqpMessage);
}