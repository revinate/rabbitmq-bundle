<?php
namespace Revinate\RabbitMqBundle\AMQP;

use OldSound\RabbitMqBundle\RabbitMq\BaseAmqp;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Exception\AMQPProtocolException;
use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\AMQP\Message\AMQPEventMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AMQPEventProducer extends BaseAmqp {
    /** @var ContainerInterface */
    protected $container;
    /** @var \OldSound\RabbitMqBundle\RabbitMq\Producer  */
    protected $producer;
    /** @var \OldSound\RabbitMqBundle\RabbitMq\Producer  */
    protected $delayedProducer;
    /** @var array */
    protected $applicationHeaders = array();

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        $this->producer = $this->container->get("old_sound_rabbit_mq.inguest_event_producer");
        $this->delayedProducer = $this->container->get("old_sound_rabbit_mq.inguest_event_delayed_producer");
    }

    /**
     * @param $message
     * @param $eventName
     */
    public function publish($message, $eventName) {
        $routingKey = $eventName;
        if (! $message instanceof AMQPEventMessage) {
            $message = new AMQPEventMessage($message, $routingKey);
        }
        $this->amqpPublish($this->producer, $message, $routingKey);
    }

    /**
     * @param AMQPEventMessage $amqpEventMessage
     * @param null $newEventName New Event Name under which to publish this message
     */
    public function rePublish(AMQPEventMessage $amqpEventMessage, $newEventName = null) {
        $newEventName = $newEventName ? $newEventName : $amqpEventMessage->getRoutingKey();
        $this->amqpPublish($this->producer, $amqpEventMessage, $newEventName);
    }

    /**
     * @param $fairnessKey
     * @param $message
     * @param $eventName
     * @param int $delayUnfairMessagesForMs
     */
    public function fairPublish($fairnessKey, $message, $eventName, $delayUnfairMessagesForMs = 10000) {
        $routingKey = $eventName;
        $amqpEventMessage = new AMQPEventMessage($message, $routingKey);
        $amqpEventMessage->setFairnessKey($fairnessKey);
        $amqpEventMessage->setUnfairnessDelay($delayUnfairMessagesForMs);
        $this->amqpPublish($this->producer, $amqpEventMessage, $routingKey);
    }

    /**
     * @param AMQPEventMessage $amqpEventMessage
     * @param int $requeueInMs Requeue in these many millisecs
     */
    public function rePublishForLater(AMQPEventMessage $amqpEventMessage, $requeueInMs) {
        $amqpEventMessage->setExpiration($requeueInMs);
        $this->amqpPublish($this->delayedProducer, $amqpEventMessage, $amqpEventMessage->getRoutingKey());
    }

    /**
     * @param \OldSound\RabbitMqBundle\RabbitMq\Producer $producer
     * @param AMQPEventMessage $amqpEventMessage
     * @param $routingKey
     * @internal param $message
     */
    protected function amqpPublish($producer, AMQPEventMessage $amqpEventMessage, $routingKey) {
        $encodedMessage = json_encode($amqpEventMessage->getMessage());
        $properties = array(
            AMQPEventMessage::CONTENT_TYPE_PROPERTY => $amqpEventMessage->getContentType(),
            AMQPEventMessage::DELIVERY_MODE_PROPERTY => $amqpEventMessage->getDeliveryMode(),
            AMQPEventMessage::APPLICATION_HEADERS_PROPERTY => $amqpEventMessage->getHeaders(),
            AMQPEventMessage::EXPIRATION_PROPERTY => $amqpEventMessage->getExpiration()
        );
        $msg = new AMQPMessage($encodedMessage, $properties);
        $producer->getChannel()->basic_publish($msg, $producer->exchangeOptions['name'], $routingKey);
    }

    /**
     * Setup Fabric
     */
    protected function autoSetupFabric() {
        if ($this->producer->autoSetupFabric) {
            $this->producer->setupFabric();
        }
    }
}