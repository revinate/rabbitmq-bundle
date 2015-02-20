<?php

namespace Revinate\RabbitMqBundle\Consumer;

use Revinate\RabbitMqBundle\Message\Message;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface ConsumerInterface
 * @package Revinate\RabbitMqBundle\Consumer
 */
interface ConsumerInterface
{
    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container);

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message $message
     * @return int
     */
    public function execute(Message $message);
}
