<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Revinate\RabbitMqBundle\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Encoder\JsonEncoder;
use Revinate\RabbitMqBundle\Message\Message;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RepublishConsumer implements ConsumerInterface, ContainerAwareInterface {

    /** @var  ContainerInterface */
    protected $container;

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return int
     */
    public function execute(Message $message)
    {
        echo "\nRouting Key:" . $message->getRoutingKey();
        echo "\nMessage: " . $message->getData() . ", retry count: " . $message->getRetryCount();
        $this->container->get("revinate.rabbit_mq.base_producer")->rePublishForAll($message);
        return DeliveryResponse::MSG_ACK;
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