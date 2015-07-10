<?php

namespace Revinate\RabbitMqBundle\Command;

use PhpAmqpLib\Exception\AMQPTimeoutException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SetupCommand
 * @package Revinate\RabbitMqBundle\Command
 */
class DeleteAllExchangesAndQueuesCommand extends ContainerAwareCommand {
    const COMMAND_NAME = 'revinate:rabbitmq:delete-all';

    /**
     * @see Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Command that deletes all queues, exchanges and their bindings')
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

        echo "\n\nDeleting Exchanges\n";
        foreach ($services->getExchanges() as $exchange) {
            $response = null;
            if (!$exchange->getManaged()) {
                echo "Skipped : ";
            } else {
                try {
                    $exchange->deleteExchange();
                    echo "Deleted: ";
                } catch (\Exception $e) {
                    echo $e->getMessage();
                }
            }
            echo $exchange->getName() . "\n" ;
        }

        echo "\n\nDeleting Queues\n";
        foreach ($services->getQueues() as $queue) {
            $response = null;
            if (!$queue->getManaged()) {
                echo "Skipped : ";
            } else {
                try {
                    $queue->deleteQueue();
                    echo "Deleted: ";
                } catch (\Exception $e) {
                    echo $e->getMessage();
                }
            }
            echo $queue->getName() . "\n" ;
        }
    }
}