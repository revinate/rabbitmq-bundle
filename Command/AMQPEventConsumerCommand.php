<?php

namespace Revinate\RabbitMqBundle\Command;

use PhpAmqpLib\Exception\AMQPTimeoutException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class AMQPEventConsumerCommand extends ContainerAwareCommand {
    const COMMAND_NAME = 'revinate:rabbitmq:consumer';

    /**
     * @see Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('inGuest AMQP Event Consumer Command')
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
        $consumerService = "revinate_rabbit_mq.consumer.$consumerName";

        /** @var \Revinate\RabbitMqBundle\AMQP\Consumer\BaseAMQPEventConsumer $baseConsumer */
        $baseConsumer = $this->getContainer()->get($consumerService);
        try {
            $baseConsumer->consume($prefetchCount);
        } catch (AMQPTimeoutException $e) {
            ;
        }
    }
}