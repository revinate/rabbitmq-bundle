<?php

namespace Revinate\RabbitMqBundle\Test\TestCase;

use Revinate\RabbitMqBundle\Message\Message;
use Revinate\RabbitMqBundle\Producer\MockProducer;

class MockProducerTest extends BaseTestCase {

    /** @var MockProducer $mockProducer */
    protected $mockProducer;

    protected function create() {
        $this->runCommand("revinate:rabbitmq:setup");
    }

    protected function clear() {
        $this->runCommand("revinate:rabbitmq:delete-all");
    }

    protected function setUp()
    {
        $this->create();
    }

    protected function tearDown() {
        $this->clear();
    }

    /**
     * @return MockProducer
     */
    protected function getMockProducer() {
        if (! $this->mockProducer instanceof MockProducer) {
            $this->mockProducer = new MockProducer();
        }
        return $this->mockProducer;
    }

    /**
     * @return Message
     */
    protected function getMockMessage() {
        return new Message("mock data", "mock_key");
    }

    public function testMockPublish() {
        $this->getMockProducer()->publish("mock message","mock_key");
        /** @var Message $msg */
        $msg = $this->getMockProducer()->consume();
        $this->assertEquals("mock message", $msg->getData());
    }

    public function testMockRePublishForAll() {
        $this->getMockProducer()->rePublishForAll($this->getMockMessage());
        /** @var Message $msg */
        $msg = $this->getMockProducer()->consume();
        $this->assertEquals("mock data", $msg->getData());
    }

    public function testMockPublishToSelf() {
        $this->getMockProducer()->publishToSelf($this->getMockMessage());
        /** @var Message $msg */
        $msg = $this->getMockProducer()->consume();
        $this->assertEquals("mock data", $msg->getData());
    }
}