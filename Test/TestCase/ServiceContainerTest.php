<?php
/**
 * Created by PhpStorm.
 * User: vinay
 * Date: 5/26/16
 * Time: 2:39 PM
 */

namespace Revinate\RabbitMqBundle\Test\TestCase;

use Revinate\RabbitMqBundle\LegacySupport\ServiceContainer;

class ServiceContainerTest extends BaseTestCase {
    /** @var  ServiceContainer */
    protected static $container;

    protected function setUp() {
        self::$container = ServiceContainer::getInstance("test", __DIR__ . "/../app/legacy_config.yml");
        // To silence output
        $this->getOutput(function() {
            self::$container->setup();
        });
    }

    protected function tearDown() {
    }

    /**
     * @param $f
     * @return string
     */
    protected function getOutput($f) {
        ob_start();
        $f();
        $output = ob_get_clean();
        return $output;
    }

    public function testGetConnection() {
        $connection = self::$container->getConnection("test");
        $this->assertNotNull($connection);
    }

    public function testGetExchange() {
        $exchange = self::$container->getExchange("test_topic.tx");
        $this->assertNotNull($exchange);
    }

    public function testGetProducer() {
        $producer = self::$container->getProducer("test_producer");
        $this->assertNotNull($producer);
    }

    public function testGetQueue() {
        $queue = self::$container->getQueue("test_zero_q");
        $this->assertNotNull($queue);
    }

    public function testGetConsumer() {
        $consumer = self::$container->getConsumer("test_zero");
        $this->assertNotNull($consumer);
    }

    public function testLegacySetup() {
        $output = $this->getOutput(function() {
            self::$container->setup();
        });
        $this->assertContains("Declared: test_zero_q", $output);
        $this->assertContains("Declaring Exchanges", $output);
    }

    public function testLegacyProduceConsume() {
        $producer = self::$container->getProducer("test_producer");
        $producer->publish("test", "test.zero");

        $consumer = self::$container->getConsumer("test_zero");
        $output = $this->getOutput(function() use($consumer) {
            $consumer->consume(1);
        });
        $this->assertContains("Routing Key:test.zero", $output);
    }
}