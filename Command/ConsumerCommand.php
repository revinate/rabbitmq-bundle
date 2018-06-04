<?php

namespace Revinate\RabbitMqBundle\Command;

use PhpAmqpLib\Exception\AMQPIOWaitException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConsumerCommand
 * @package Revinate\RabbitMqBundle\Command
 */
class ConsumerCommand extends ContainerAwareCommand {
    const COMMAND_NAME = 'revinate:rabbitmq:consumer';

    /**
     * @see Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Default Consumer Command')
            ->addArgument('consumerName', InputArgument::REQUIRED, 'Consumer Name')
            ->addArgument('target', InputArgument::OPTIONAL, 'Target number of messages to consume', 1)
        ;
    }

    /**
     * @see Symfony\Component\Console\Command\Command::initialize()
     */
    protected function initialize(InputInterface $input, OutputInterface $output) {
    }

    /**
     * @see Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $consumerName = $input->getArgument('consumerName');
        $target = intval($input->getArgument('target'));
        $target = $target ?: 1;
        $consumerService = "revinate_rabbit_mq.consumer.$consumerName";

        try {
            // Create batch or single consumer based on the type of consumer
            /** @var \Revinate\RabbitMqBundle\Consumer\Consumer $consumer */
            $consumer = $this->getContainer()->get($consumerService);
            $consumer->consume($target);
        } catch (AMQPTimeoutException $e) {
            ;
        } catch (AMQPIOWaitException $e) {
            ;
        }
    }
}