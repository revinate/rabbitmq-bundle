<?php

namespace Revinate\RabbitMqBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;

/**
 * Class RevinateRabbitMqExtension
 * @package Revinate\RabbitMqBundle\DependencyInjection
 */
class RevinateRabbitMqExtension extends Extension
{
    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * @var Boolean Whether the data collector is enabled
     */
    private $collectorEnabled;

    private $channelIds = array();

    private $config = array();

    public function load(array $configs, ContainerBuilder $container)
    {
        $this->container = $container;

        //$loader = new XmlFileLoader($this->container, new FileLocator(array(__DIR__ . '/../Resources/config')));
        //$loader->load('rabbitmq.xml');

        $configuration = new Configuration();
        $this->config = $this->processConfiguration($configuration, $configs);
    }

}
