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
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('revinate_rabbit_mq');
        $rootNode
            ->children()
                ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
        ;

        $this->addConnections($rootNode);
        $this->addExchanges($rootNode);
        $this->addQueues($rootNode);
        $this->addProducers($rootNode);
        $this->addConsumers($rootNode);

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $node
     */
    protected function addExchanges(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('exchanges')
                    ->useAttributeAsKey('key')
                    ->canBeUnset()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('connection')->isRequired()->end()
                            ->scalarNode('type')->isRequired()->end()
                            ->booleanNode('passive')->defaultValue(false)->end()
                            ->booleanNode('durable')->defaultValue(true)->end()
                            ->booleanNode('auto_delete')->defaultValue(false)->end()
                            ->booleanNode('internal')->defaultValue(false)->end()
                            ->booleanNode('nowait')->defaultValue(false)->end()
                            ->booleanNode('declare')->defaultValue(true)->end()
                            ->variableNode('arguments')->defaultNull()->end()
                            ->scalarNode('ticket')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param ArrayNodeDefinition $node
     */
    protected function addQueues(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('queues')
                    ->useAttributeAsKey('key')
                    ->canBeUnset()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('connection')->isRequired()->end()
                            ->scalarNode('exchange')->isRequired()->end()
                            ->booleanNode('passive')->defaultFalse()->end()
                            ->booleanNode('durable')->defaultTrue()->end()
                            ->booleanNode('exclusive')->defaultFalse()->end()
                            ->booleanNode('auto_delete')->defaultFalse()->end()
                            ->booleanNode('nowait')->defaultFalse()->end()
                            ->variableNode('arguments')->defaultNull()->end()
                            ->scalarNode('ticket')->defaultNull()->end()
                            ->arrayNode('routing_keys')
                                ->prototype('scalar')->end()
                                ->defaultValue(array())
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param ArrayNodeDefinition $node
     */
    protected function addConnections(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('connections')
                    ->useAttributeAsKey('key')
                    ->canBeUnset()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('host')->defaultValue('localhost')->end()
                            ->scalarNode('port')->defaultValue(5672)->end()
                            ->scalarNode('user')->defaultValue('guest')->end()
                            ->scalarNode('password')->defaultValue('guest')->end()
                            ->scalarNode('vhost')->defaultValue('/')->end()
                            ->booleanNode('lazy')->defaultFalse()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param ArrayNodeDefinition $node
     */
    protected function addProducers(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('producers')
                    ->canBeUnset()
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('connection')->defaultValue('default')->end()
                            ->scalarNode('exchange')->defaultValue(null)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param ArrayNodeDefinition $node
     */
    protected function addConsumers(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('consumers')
                    ->canBeUnset()
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('connection')->defaultValue('default')->end()
                            ->scalarNode('queue')->defaultValue(null)->end()
                            ->scalarNode('callback')->isRequired()->end()
                            ->scalarNode('idle_timeout')->end()
                            ->arrayNode('qos_options')
                                ->canBeUnset()
                                ->children()
                                    ->scalarNode('prefetch_size')->defaultValue(0)->end()
                                    ->scalarNode('prefetch_count')->defaultValue(0)->end()
                                    ->booleanNode('global')->defaultFalse()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
