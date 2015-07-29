<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Revinate\RabbitMqBundle\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Message\Message;
use Revinate\RabbitMqBundle\Producer\BaseProducer;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PublishToSelfConsumer extends BaseConsumer implements ConsumerInterface, ContainerAwareInterface {

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return int
     */
    public function execute(Message $message)
    {
        /** @var BaseProducer $producer */
        $producer = $this->container->get("revinate.rabbit_mq.base_producer");
        echo "\nRouting Key:" . $message->getRoutingKey();
        echo "\nMessage: " . $this->toString($message->getData());
        echo "\nRetry count: " . $message->getRetryCount();
        if ($message->getRetryCount() < 5) {
            $message->incrementRetryCount();
            $producer->publishToSelf($message);
        }
        return DeliveryResponse::MSG_ACK;
    }

}