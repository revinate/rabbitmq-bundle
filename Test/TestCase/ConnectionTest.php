<?php

use Revinate\RabbitMqBundle\Test\TestCase\BaseTestCase;

class ConnectionTest extends BaseTestCase {
    protected function create() {
        $this->runCommand("revinate:rabbitmq:setup");
    }

    protected function clear() {
        $this->runCommand("revinate:rabbitmq:delete-all");
    }

    protected function setUp() {
        $this->create();
    }

    protected function tearDown() {
        $this->clear();
    }

    public function testReconnection() {
        $producer = $this->getContainer()->get("revinate_rabbit_mq.producer.test_producer");
        $producer->publish("test message", "test.one");
        $producer->getConnection()->close();
        $producer->publish("test message", "test.one");

        $consumer = $this->getContainer()->get("revinate_rabbit_mq.consumer.test_one");
        ob_start();
        $consumer->consume(2);
        $output = ob_get_clean();
        $this->assertEquals(2, $this->countString($output, "Routing Key:test.one"), $this->debug($output));
    }
}