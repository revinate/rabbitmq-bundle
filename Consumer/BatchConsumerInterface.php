<?php

namespace Revinate\RabbitMqBundle\Consumer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface BatchConsumerInterface
 * @package Revinate\RabbitMqBundle\Consumer
 */
interface BatchConsumerInterface
{
    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container);

    /**
     * @param \Revinate\RabbitMqBundle\Message\Message[] $messages
     * @return int
     */
    public function execute($messages);
}
