<?php

namespace Revinate\RabbitMqBundle\Consumer;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use Revinate\RabbitMqBundle\Decoder\JsonDecoder;
use Revinate\RabbitMqBundle\Exchange\Exchange;
use Revinate\RabbitMqBundle\Lib\TextHelper;
use Revinate\RabbitMqBundle\Message\Message;
use Revinate\RabbitMqBundle\Producer\Producer;
use Revinate\RabbitMqBundle\Queue\Queue;

class RPCConsumer {
    /** @var  Producer */
    protected $producer;
    /** @var AMQPChannel */
    protected $channel;
    /** @var AMQPConnection  */
    protected $connection;
    /** @var string */
    protected $queueName;

    /**
     * @param Producer $producer
     */
    public function __construct(Producer $producer) {
        $this->producer = $producer;
        $this->connection = $this->producer->getConnection();
    }

    /**
     * @param string|array|Message $data
     * @param string $routingKey
     * @return string temporary queue name
     */
    public function call($data, $routingKey) {
        $message = $data instanceof Message ? $data : new Message($data, $routingKey);
        $queueName = $this->declareTemporaryQueue();
        $message->setReplyTo($queueName);
        $this->producer->publish($message, $routingKey);
        return $queueName;
    }

    /**
     * @param $queueName
     * @param $timeoutInSec
     * @param callback $callback
     */
    public function consume($queueName, $timeoutInSec, $callback) {
        $exchange = new Exchange("", $this->connection, "topic", false, true, false, false, false, null, null, false);
        $queue = new Queue($queueName, $exchange, false, false, true, true, false, null, array(), null, false);
        $consumer = new Consumer(null, $queueName, array($queue));
        $consumer->setCallbacks(array($callback));
        $consumer->setQosOptions(array('prefetch_size' => 0, 'prefetch_count' => 1, 'global' => false));
        $consumer->setDecoder(new JsonDecoder());
        $consumer->setIdleTimeout($timeoutInSec);
        $consumer->consume(1);
    }

    /**
     * @return string
     */
    public function declareTemporaryQueue() {
        $name = "rpc_". TextHelper::getRandomString(32) ."_q";
        $this->producer->getChannel()->queue_declare($name, false, false, true, true);
        return $name;
    }
}