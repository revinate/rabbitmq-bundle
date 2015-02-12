<?php

namespace OldSound\RabbitMqBundle\Command;

use PhpAmqpLib\Exception\AMQPTimeoutException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class AMQPEventConsumerCommand extends ContainerAwareCommand {
    const COMMAND_NAME = 'revinate:amqp_event:consumer';

    /**
     * @see Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Revinate AMQP Event Consumer Command')
            ->addArgument('consumerName', InputArgument::REQUIRED, 'Consumer Name')
            ->addArgument('prefetchCount', InputArgument::OPTIONAL, 'Prefetch Count')
        ;
    }

    /**
     * @see Symfony\Component\Console\Command\Command::initialize()
     */
    protected function initialize(InputInterface $input, OutputInterface $output) {
        parent::initialize($input, $output);
    }

    /**
     * @see Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $consumerName = $input->getArgument('consumerName');
        $prefetchCount = intval($input->getArgument('prefetchCount'));
        $prefetchCount = $prefetchCount ?: 1;
        $consumerService = 'old_sound_rabbit_mq.' . $consumerName . '_consumer';

        /** @var \OldSound\RabbitMqBundle\RabbitMq\Consumer $consumer */
        $consumer = $this->getContainer()->get($consumerService);
        try {
            $consumer->consume($prefetchCount);
        } catch (AMQPTimeoutException $e) {
            ;
        }
    }
}