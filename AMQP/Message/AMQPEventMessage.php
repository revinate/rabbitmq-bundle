<?php

namespace Revinate\RabbitMqBundle\AMQP\Message;

use Revinate\SharedBundle\Lib\DateHelper;
use Revinate\SharedBundle\Lib\Tools;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class AMQPEventMessage
 * @package Revinate\RabbitMqBundle\AMQP\Message
 * @TODO: Add support for original event id
 * @TODO: Add Type
 * @TODO: Way to convert entity into message
 */
class AMQPEventMessage {
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
    protected $message;

    /** @var array message headers */
    protected $headers = array();

    /**
     * @param $message
     * @param $routingKey
     * @param $headers
     */
    public function __construct($message, $routingKey, $headers = array()) {
        $this->message = $message;
        $this->addHeader('routingKey', $routingKey);
        if (!empty($headers)) {
            $this->setHeaders($headers);
        } else {
            $this->addHeader('id', Tools::getRandomString(8));
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
    public function getMessage()
    {
        return $this->message;
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
    public function setHeaders($headers = null) {
        if ($headers) {
            $this->headers = $headers;
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
     * @param string $stringValue
     */
    public function addHeader($key, $stringValue) {
        $this->headers[$key] = array('S', $stringValue);
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
     * @param $numberOfEnqueueAttempts
     */
    public function setNumberOfEnqueueAttempts($numberOfEnqueueAttempts) {
        $this->addHeader('numberOfEnqueueAttempts', $numberOfEnqueueAttempts);
    }

    /**
     * @return string
     */
    public function getNumberOfEnqueueAttempts() {
        if (is_null($this->getHeader('numberOfEnqueueAttempts'))) {
            // by default if should be 1
            return 1;
        }
        return $this->getHeader('numberOfEnqueueAttempts');
    }

    /**
     * @return string
     */
    public function __toString() {
        return json_encode(array(
            'id' => $this->getId(),
            'routingKey' => $this->getRoutingKey(),
            'fairnessKey' => $this->getFairnessKey(),
            'message' => $this->getMessage(),
            'createdAt' => $this->getCreatedAt(),
            'dequeuedAt' => $this->getDequeuedAt(),
            'processedAt' => $this->getProcessedAt(),
            'processTime' => $this->getProcessTime(),
            'dequeueDelay' => $this->getDequeueDelay(),
            'numberOfEnqueueAttempts' => $this->getNumberOfEnqueueAttempts()
        ));
    }
}