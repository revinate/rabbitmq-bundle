<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Revinate\RabbitMqBundle\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\Consumer\DeliveryResponse;
use Revinate\RabbitMqBundle\Encoder\JsonEncoder;
use Revinate\RabbitMqBundle\Message\Message;
use Revinate\RabbitMqBundle\Producer\BaseProducer;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RepublishConsumer extends BaseConsumer implements ConsumerInterface {

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return int
     */
    public function execute(Message $message)
    {
        echo "\nRouting Key:" . $message->getRoutingKey();
        echo "\nMessage: " . $message->getData();
        echo "\nRetry count: " . $message->getRetryCount();
        /** @var BaseProducer $producer */
        $producer = $this->container->get("revinate.rabbit_mq.base_producer");
        $producer->rePublishForAll($message);
        return DeliveryResponse::MSG_ACK;
    }
}