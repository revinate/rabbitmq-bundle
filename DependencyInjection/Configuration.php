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
    /**
     * @return TreeBuilder
     */
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
    protected function addConnections(ArrayNodeDefinition $node)
    {
        /** @noinspection PhpUndefinedMethodInspection */
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
    protected function addExchanges(ArrayNodeDefinition $node)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $node
            ->children()
                ->arrayNode('exchanges')
                    ->useAttributeAsKey('key')
                    ->canBeUnset()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('connection')->isRequired()->end()
                            ->scalarNode('type')->defaultValue('topic')->end()
                            ->booleanNode('passive')->defaultFalse()->end()
                            ->booleanNode('durable')->defaultTrue()->end()
                            ->booleanNode('auto_delete')->defaultFalse()->end()
                            ->booleanNode('internal')->defaultFalse()->end()
                            ->booleanNode('nowait')->defaultFalse()->end()
                            ->booleanNode('managed')->defaultTrue()->end()
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
                            ->scalarNode('exchange')->isRequired()->end()
                            ->booleanNode('passive')->defaultFalse()->end()
                            ->booleanNode('durable')->defaultTrue()->end()
                            ->booleanNode('exclusive')->defaultFalse()->end()
                            ->booleanNode('auto_delete')->defaultFalse()->end()
                            ->booleanNode('nowait')->defaultFalse()->end()
                            ->booleanNode('managed')->defaultTrue()->end()
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
    protected function addProducers(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('producers')
                    ->canBeUnset()
                    ->useAttributeAsKey('key')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('exchange')->isRequired()->end()
                            ->scalarNode('encoder')->defaultValue("revinate.rabbit_mq.encoder.json")->end()
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
                            ->scalarNode('queue')->defaultValue(null)->end()
                            ->arrayNode('queues')->prototype('scalar')->end()->defaultValue(array())->end()
                            ->scalarNode('callback')->defaultValue(null)->end()
                            ->arrayNode('callbacks')->prototype('scalar')->end()->defaultValue(array())->end()
                            ->scalarNode('idle_timeout')->defaultValue(0)->end()
                            ->scalarNode('message_class')->defaultValue(null)->end()
                            ->scalarNode('batch_size')->defaultValue(null)->end()
                            ->scalarNode('buffer_wait')->defaultValue(1000)->end()
                            ->scalarNode('decoder')->defaultValue("revinate.rabbit_mq.decoder.json")->end()
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
