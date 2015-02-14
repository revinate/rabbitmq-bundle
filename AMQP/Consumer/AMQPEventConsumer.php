<?php
namespace Revinate\RabbitMqBundle\AMQP\Consumer;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Consumer\ConsumerInterface;
use Revinate\RabbitMqBundle\AMQP\Consumer\DeliveryResponse;
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
class AMQPEventConsumer implements ConsumerInterface {

    /** @var ContainerInterface */
    protected $container;
    /** @var FairnessAlgorithmInterface */
    protected $fairnessAlgorithm;

    /**
     * @param $container
     */
    public function __construct($container) {
        $this->container = $container;
        $this->fairnessAlgorithm = new NotConsecutiveFairnessAlgorithm();
    }

    /**
     * @param AMQPMessage $msg
     * @return int
     */
    public function execute(AMQPMessage $msg) {
        $dispatcher = $this->container->get('event_dispatcher');
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
                return DeliveryResponse::MSG_REJECT_REQUEUE;
            }
        } catch (RejectRequeueException $e) {
            error_log("Event Requeued due to processing error: " . $e->getMessage());
            return DeliveryResponse::MSG_REJECT_REQUEUE;
        } catch (RejectDropException $e) {
            error_log("Event Dropped due to processing error: " . $e->getMessage());
            return DeliveryResponse::MSG_REJECT;
        }
        $this->fairnessAlgorithm->onMessageProcessed($amqpEventMessage);
        return DeliveryResponse::MSG_ACK;
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