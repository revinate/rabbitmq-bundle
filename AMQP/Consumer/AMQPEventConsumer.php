<?php
namespace Revinate\RabbitMqBundle\AMQP\Consumer;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\AMQP\Exceptions\RejectDropException;
use Revinate\RabbitMqBundle\AMQP\Exceptions\RejectRequeueException;
use Revinate\RabbitMqBundle\AMQP\FairnessAlgorithms\FairnessAlgorithmInterface;
use Revinate\RabbitMqBundle\AMQP\FairnessAlgorithms\NotConsecutiveFairnessAlgorithm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class AMQPEventConsumer
 * @package Revinate\RabbitMqBundle\AMQP\Consumer
 * @TODO: Support Batched Consumer
 */
class AMQPEventConsumer extends BaseAMQPEventConsumer implements ConsumerInterface {
    /** @var FairnessAlgorithmInterface */
    protected $fairnessAlgorithm;

    /**
     * @param $name
     * @param string $connection
     * @param string $queue
     */
    public function __construct($name, $connection, $queue) {
        $this->fairnessAlgorithm = new NotConsecutiveFairnessAlgorithm();
        parent::__construct($name, $connection, $queue);
    }

    /**
     * @param AMQPMessage $msg
     * @return int
     */
    public function execute(AMQPMessage $msg) {
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $routingKey = $msg->delivery_info['routing_key'];
        $eventName = $this->getEventNameFromRoutingKey($routingKey);
        $amqpEventMessage = $this->getAMQPEventMessage($msg);
        $amqpEventMessage->setDequeuedAt(new \DateTime('now'));
        try {
            if (!$this->isFairPublishMessage($amqpEventMessage) || $this->fairnessAlgorithm->isFairToProcess($amqpEventMessage)) {
                $dispatcher->dispatch($eventName, $amqpEventMessage);
                $amqpEventMessage->setProcessedAt(new \DateTime('now'));
            } else {
                error_log("Event Requeued due to unfairness. Key: " . $amqpEventMessage->getFairnessKey());
                return ConsumerInterface::MSG_REJECT_REQUEUE;
            }
        } catch (RejectRequeueException $e) {
            error_log("Event Requeued due to processing error: " . $e->getMessage());
            return ConsumerInterface::MSG_REJECT_REQUEUE;
        } catch (RejectDropException $e) {
            error_log("Event Dropped due to processing error: " . $e->getMessage());
            return ConsumerInterface::MSG_REJECT;
        }
        $this->fairnessAlgorithm->onMessageProcessed($amqpEventMessage);
        return ConsumerInterface::MSG_ACK;
    }

    /**
     * @param $routingKey
     * @throws \Exception
     * @return string
     */
    protected function getEventNameFromRoutingKey($routingKey) {
        return $routingKey;
    }
}