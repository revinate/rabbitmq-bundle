<?php
namespace Revinate\RabbitMqBundle\AMQP;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Exceptions\RejectDropException;
use Revinate\RabbitMqBundle\AMQP\Exceptions\RejectRequeueException;
use Revinate\RabbitMqBundle\AMQP\FairnessAlgorithms\FairnessAlgorithmInterface;
use Revinate\RabbitMqBundle\AMQP\FairnessAlgorithms\NotConsecutiveFairnessAlgorithm;
use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;
use Revinate\RabbitMqBundle\Logging\DebugKeys;
use Revinate\RabbitMqBundle\Logging\EventLogger;
use Revinate\RabbitMqBundle\Logging\EventType;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\Event;

class AMQPEventConsumer implements ConsumerInterface {
    /** @var ContainerInterface */
    protected $container;
    /** @var AMQPEventProducer $producer */
    protected $producer;
    /** @var FairnessAlgorithmInterface */
    protected $fairnessAlgorithm;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        $this->producer = $this->container->get('inguest.amqp_event.producer');
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
                //EventLogger::log(null, EventType::MESSAGE_REQUEUE_UNFAIR, array(DebugKeys::EVENT_MESSAGE_ID => $amqpEventMessage->getId()));
                $this->producer->rePublishForLater($amqpEventMessage, $amqpEventMessage->getUnfairnessDelay());
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

    /**
     * @param AMQPMessage $msg
     * @return AMQPEventMessage|Event
     */
    protected function getAMQPEventMessage(AMQPMessage $msg) {
        $routingKey = $msg->delivery_info['routing_key'];
        $properties = $msg->get_properties();
        $headers = $properties['application_headers'];
        return new AMQPEventMessage(json_decode($msg->body, true), $routingKey, $headers);
    }

    /**
     * @param AMQPEventMessage $amqpEventMessage
     * @return bool
     */
    protected function isFairPublishMessage(AMQPEventMessage $amqpEventMessage) {
         return !is_null($amqpEventMessage->getFairnessKey());
    }
}