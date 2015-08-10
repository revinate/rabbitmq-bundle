<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Revinate\RabbitMqBundle\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\Message\Message;
use Revinate\RabbitMqBundle\Producer\BaseProducer;
use Revinate\RabbitMqBundle\Producer\DefaultExchangeProducer;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RpcServer implements ConsumerInterface, ContainerAwareInterface {

    /** @var  ContainerInterface */
    protected $container;

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return int
     */
    public function execute(Message $message)
    {
        $properties = $message->getAmqpMessage()->get_properties();
        $replyTo = $properties['reply_to'];
        echo "\nReplying to RPC Call with reply_to queue: "  . $replyTo;

        /** @var DefaultExchangeProducer $producer */
        $producer = $this->container->get("revinate.rabbit_mq.default_exchange_producer");
        $producer->setChannel($message->getQueue()->getChannel());
        $producer->publishToDefaultExchange($message, $replyTo);
    }

    /**
     * Sets the Container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     *
     * @api
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }
}