<?php

namespace Revinate\RabbitMqBundle\Command;

use PhpAmqpLib\Exception\AMQPTimeoutException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SetupCommand
 * @package Revinate\RabbitMqBundle\Command
 */
class SetupCommand extends ContainerAwareCommand {
    const COMMAND_NAME = 'revinate:rabbitmq:setup';

    /**
     * @see Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Command thats setups all queues, exchanges and their bindings')
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
        $services = $this->getContainer()->get('revinate.rabbit_mq.services');

        echo "\n\nDeclaring Exchanges\n";
        foreach ($services->getExchanges() as $exchange) {
            $response = null;
            if (!$exchange->getManaged()) {
                echo "Skipped : ";
            } else {
                $exchange->declareExchange();
                echo "Declared: ";
            }
            echo $exchange->getName() . "\n" ;
        }

        echo "\n\nDeclaring Queues\n";
        foreach ($services->getQueues() as $queue) {
            $response = null;
            if (!$queue->getManaged()) {
                echo "Skipped : ";
            } else {
                $queue->declareQueue();
                echo "Declared: ";
            }
            echo $queue->getName() . "\n" ;
        }
    }
}