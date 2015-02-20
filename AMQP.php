<?php

namespace Revinate\RabbitMqBundle;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Connection\AMQPLazyConnection;

abstract class Amqp {
    protected $conn;
    protected $ch;
    protected $consumerTag;
    protected $exchangeDeclared = false;
    protected $queueDeclared = false;
    protected $routingKey = '';
    protected $autoSetupFabric = true;
    protected $basicProperties = array('content_type' => 'text/plain', 'delivery_mode' => 2);

    /**
     * @param AMQPConnection   $conn
     * @param AMQPChannel|null $ch
     * @param null             $consumerTag
     */
    public function __construct(AMQPConnection $conn, AMQPChannel $ch = null, $consumerTag = null)
    {
        $this->conn = $conn;
        $this->ch = $ch;

        if (!($conn instanceof AMQPLazyConnection)) {
            $this->getChannel();
        }

        $this->consumerTag = empty($consumerTag) ? sprintf("PHPPROCESS_%s_%s", gethostname(), getmypid()) : $consumerTag;
    }

    public function __destruct()
    {
        if ($this->ch) {
            $this->ch->close();
        }

        if ($this->conn->isConnected()) {
            $this->conn->close();
        }
    }

    /**
     * @return AMQPChannel
     */
    public function getChannel()
    {
        if (empty($this->ch)) {
            $this->ch = $this->conn->channel();
        }

        return $this->ch;
    }

    /**
     * @param  string $routingKey
     * @return void
     */
    public function setRoutingKey($routingKey)
    {
        $this->routingKey = $routingKey;
    }

}
