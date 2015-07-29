<?php

namespace Revinate\RabbitMqBundle\Test\Consumer;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BaseConsumer implements ContainerAwareInterface {

    /** @var  ContainerInterface */
    protected $container;

    protected function toString($data) {
        if (is_array($data)) {
            return json_encode($data);
        }
        if (is_object($data)) {
            return serialize($data);
        }
        return $data;
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