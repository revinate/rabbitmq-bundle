<?php

namespace Revinate\RabbitMqBundle\Message;

use PhpAmqpLib\Message\AMQPMessage;
use Revinate\RabbitMqBundle\Consumer\Consumer;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\Lib\DateHelper;
use Revinate\RabbitMqBundle\Lib\TextHelper;
use Revinate\RabbitMqBundle\Queue\Queue;

/**
 * Class Message
 * @package Revinate\RabbitMqBundle\Message
 * @TODO: Add support for original event id
 * @TODO: Add Type
 * @TODO: Way to convert entity into message
 */
class Message {
    const CONTENT_TYPE_PROPERTY = 'content_type';
    const DELIVERY_MODE_PROPERTY = 'delivery_mode';
    const APPLICATION_HEADERS_PROPERTY = 'application_headers';
    const EXPIRATION_PROPERTY = 'expiration';

    // Add way a deadlettered message that has older queue name
    /** @var  string */
    protected $contentType = 'text/plain';
    /** @var string */
    protected $deliveryMode = 2;
    /** @var  int */
    protected $expiration;
    /** @var  array|string|int */
    protected $data;
    /** @var Consumer */
    protected $consumer;
    /** @var Queue */
    protected $queue;
    /** @var  AMQPMessage */
    protected $amqpMessage;
    /** @var  string */
    protected $originalRoutingKey;

    /** @var array message headers */
    protected $headers = array();

    /**
     * @param $data
     * @param $routingKey
     * @param $headers
     */
    public function __construct($data, $routingKey, $headers = array()) {
        $this->data = $data;
        $this->setRoutingKey($routingKey);
        $this->setOriginalRoutingKey($routingKey);
        $this->setCreatedAt(new \DateTime('now'));
        if (!empty($headers)) {
            $this->setHeaders($headers);
        } else {
            $this->addHeader('id', TextHelper::getRandomString(16));
        }
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt($createdAt) {
        $this->addHeader('createdAt', $createdAt->format('c'));
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return DateHelper::convertDateToDateTime($this->getHeader('createdAt'));
    }

    /**
     * Increments retry count when message is requeued
     */
    public function incrementRetryCount() {
        $this->addHeader('retryCount', $this->getRetryCount() + 1);
    }

    /**
     * @return int
     */
    public function getRetryCount() {
        return (int)$this->getHeader('retryCount');
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->getHeader('id');
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param string $routingKey
     */
    public function setRoutingKey($routingKey) {
        $this->addHeader('routingKey', $routingKey);
    }

    /**
     * @return string
     */
    public function getRoutingKey()
    {
        return $this->getHeader('routingKey');
    }

    /**
     * @param \DateTime $processedAt
     */
    public function setProcessedAt($processedAt)
    {
        $this->addHeader('processedAt', $processedAt->format('c'));
    }

    /**
     * @return \DateTime
     */
    public function getProcessedAt()
    {
        return DateHelper::convertDateToDateTime($this->getHeader('processedAt'));
    }

    /**
     * @param \DateTime $dequeuedAt
     */
    public function setDequeuedAt($dequeuedAt)
    {
        $this->addHeader('dequeuedAt', $dequeuedAt->format('c'));
    }

    /**
     * @return \DateTime
     */
    public function getDequeuedAt()
    {
        return DateHelper::convertDateToDateTime($this->getHeader('dequeuedAt'));
    }

    /**
     * @return int
     */
    public function getDequeueDelay() {
        return $this->getDequeuedAt() && $this->getCreatedAt() ? $this->getDequeuedAt()->getTimestamp() - $this->getCreatedAt()->getTimestamp() : null;
    }

    /**
     * @return int
     */
    public function getProcessTime() {
        return $this->getProcessedAt() && $this->getDequeuedAt() ? $this->getProcessedAt()->getTimestamp() - $this->getDequeuedAt()->getTimestamp() : null;
    }

    /**
     * @param $headers
     */
    protected function setHeaders($headers = null) {
        if ($headers) {
            $this->headers = array();
            foreach ($headers as $index => $header) {
                $this->headers[$index] = is_array($header) ? $header : array('S', $header);
            }
        }
    }
    /**
     * @return array
     */
    public function getHeaders() {
        return $this->headers;
    }

    /**
     * @param string $key
     * @param string $value
     */
    public function addHeader($key, $value) {
        $type = 'S';
        if (is_array($value)) {
            $type = 'A';
        }
        $this->headers[$key] = array('A', $stringValue);
    }

    /**
     * @param $key
     * @return null
     */
    public function getHeader($key) {
        return isset($this->headers[$key]) ? $this->headers[$key][1] : null;
    }

    /**
     * @param string $contentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @param string $deliveryMode
     */
    public function setDeliveryMode($deliveryMode)
    {
        $this->deliveryMode = $deliveryMode;
    }

    /**
     * @return string
     */
    public function getDeliveryMode()
    {
        return $this->deliveryMode;
    }

    /**
     * @param int $expiration
     */
    public function setExpiration($expiration)
    {
        $this->expiration = $expiration;
    }

    /**
     * @return int
     */
    public function getExpiration()
    {
        return $this->expiration;
    }

    /**
     * @param string $fairnessKey
     */
    public function setFairnessKey($fairnessKey) {
        $this->addHeader('fairnessKey', $fairnessKey);
    }

    /**
     * @return string
     */
    public function getFairnessKey() {
        return $this->getHeader('fairnessKey');
    }

    /**
     * @param int $delayInMs
     */
    public function setUnfairnessDelay($delayInMs) {
        $this->addHeader('unfairnessDelay', $delayInMs);
    }

    /**
     * @return int delay in ms
     */
    public function getUnfairnessDelay() {
        return $this->getHeader('unfairnessDelay');
    }

    /**
     * @param \Revinate\RabbitMqBundle\Consumer\Consumer $consumer
     */
    public function setConsumer($consumer)
    {
        $this->consumer = $consumer;
    }

    /**
     * @return \Revinate\RabbitMqBundle\Consumer\Consumer
     */
    public function getConsumer()
    {
        return $this->consumer;
    }

    /**
     * @param \Revinate\RabbitMqBundle\Queue\Queue $queue
     */
    public function setQueue($queue) {
        $this->queue = $queue;
    }

    /**
     * @return \Revinate\RabbitMqBundle\Queue\Queue
     */
    public function getQueue() {
        return $this->queue;
    }

    /**
     * @param \PhpAmqpLib\Message\AMQPMessage $amqpMessage
     */
    public function setAmqpMessage($amqpMessage)
    {
        $this->amqpMessage = $amqpMessage;
    }

    /**
     * @return \PhpAmqpLib\Message\AMQPMessage
     */
    public function getAmqpMessage()
    {
        return $this->amqpMessage;
    }

    /**
     * @param string $originalRoutingKey
     */
    public function setOriginalRoutingKey($originalRoutingKey)
    {
        $this->addHeader('originalRoutingKey', $originalRoutingKey);
    }

    /**
     * @return string
     */
    public function getOriginalRoutingKey()
    {
        return $this->getHeader('originalRoutingKey');
    }

    /**
     * @return string|null
     */
    public function getDeliveryTag() {
        return $this->amqpMessage ? $this->amqpMessage->delivery_info['delivery_tag'] : null;
    }

    /**
     * @return null|string
     */
    public function isRedelivered() {
        return $this->amqpMessage ? $this->amqpMessage->delivery_info['redelivered'] : null;
    }

    /**
     * @return null|string
     */
    public function getExchangeName() {
        return $this->amqpMessage ? $this->amqpMessage->delivery_info['exchange'] : null;
    }


    /**
     * @return string
     */
    public function __toString() {
        return json_encode(array(
            'id' => $this->getId(),
            'routingKey' => $this->getRoutingKey(),
            'originalRoutingKey' => $this->getOriginalRoutingKey(),
            'exchangeName' => $this->getExchangeName(),
            'fairnessKey' => $this->getFairnessKey(),
            'data' => $this->getData(),
            'createdAt' => $this->getCreatedAt(),
            'dequeuedAt' => $this->getDequeuedAt(),
            'processedAt' => $this->getProcessedAt(),
            'processTime' => $this->getProcessTime(),
            'dequeueDelay' => $this->getDequeueDelay(),
            'retryCount' => $this->getRetryCount(),
        ));
    }
}