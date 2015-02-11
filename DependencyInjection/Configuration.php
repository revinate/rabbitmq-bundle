<?php

namespace Revinate\RabbitMqBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Class Configuration
 * @package Revinate\RabbitMqBundle\DependencyInjection
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $tree = new TreeBuilder();

        $rootNode = $tree->root('revinate_rabbitmq');

        $rootNode
            ->children()
                ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
                ->booleanNode('enable_collector')->defaultValue(false)->end()
                ->booleanNode('sandbox')->defaultValue(false)->end()
            ->end()
        ;

        return $tree;
    }

}
