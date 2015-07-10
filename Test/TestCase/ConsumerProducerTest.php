<?php
namespace Revinate\RabbitMqBundle\Test\TestCase;

use Revinate\RabbitMqBundle\Producer\Producer;
use Revinate\RabbitMqBundle\Test\Message\CustomMessage;
use Revinate\RabbitMqBundle\Test\TestCase\BaseTestCase;

class ConsumerProducerTest extends BaseTestCase
{
    protected function debug($results) {
        return "Results: " . print_r($results, true);
    }

    protected function produceMessages($count, $routingKey, $message = null, $producerName = "test_producer") {
        /** @var Producer $producer */
        $producer = $this->getContainer()->get("revinate_rabbit_mq.producer.$producerName");
        for ($i = 0; $i < $count; $i++) {
            $message = ! is_null($message) ? $message : ($i . ". " . $this->getRandomString(32));
            $producer->publish($message, $routingKey);
        }
    }

    protected function consumeMessages($consumerName, $count) {
        return $this->runCommand("revinate:rabbitmq:consumer", array("consumerName" => $consumerName, "target" => $count));
    }

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

    protected function teardown() {
        $this->clear();
    }

    /**
     * @param $payload
     * @param $string
     * @return bool
     */
    protected function has($payload, $string) {
        return strpos($payload, $string) !== false;
    }

    protected function countString($payload, $string) {
        return substr_count($payload, $string);
    }

    public function testBasicProducerAndSingleConsumer() {
        $this->produceMessages(1, "test.zero");
        $output = $this->consumeMessages("test_zero", 1);
        $this->assertTrue($this->has($output, "Routing Key:test.zero"), $this->debug($output));
    }

    public function testBulkConsumer() {
        $count = 40;
        $this->produceMessages($count, "test.two");
        $output = $this->consumeMessages("test_two", $count);
        $this->assertSame($count, $this->countString($output, "Routing Key:test.two"), $this->debug($output));
    }

    public function xtestBulkConsumerWithBufferWait() {
        $count = 20;
        $this->produceMessages($count, "test.two");
        usleep(50000);
        $this->produceMessages($count, "test.two");
        $output = $this->consumeMessages("test_two", $count * 2);
        $this->assertSame($count * 2, $this->countString($output, "Routing Key:test.two"), $this->debug($output));
        $this->assertTrue($this->countString($output, "Returning from Bulk execute") > 1, $this->debug($output));
    }

    public function testConsumerWithCustomMessage() {
        $count = 2;
        $customMessage = new CustomMessage("custom Data", "one.three");
        $customMessage->setCustomHeader("customHeader", "customHeaderValue");
        $this->produceMessages($count, "test.three", $customMessage);
        $output = $this->consumeMessages("test_three", $count);
        $this->assertSame($count, $this->countString($output, "Header: customHeader ="), $this->debug($output));
    }

    public function testConsumeWithStringEncoder() {
        $count = 10;
        $this->produceMessages($count, "test.one", "String Message", "test_string_encoder");
        $output = $this->consumeMessages("test_string_decoder", $count);
        $this->assertTrue($this->has($output, "String Message"), $this->debug($output));
    }

    public function testConsumeWithJsonEncoder() {
        $this->produceMessages(1, "test.one", array("type" => "JSON Message"), "test_producer");
        $output = $this->consumeMessages("test_one", 1);
        $this->assertTrue($this->has($output, '{"type":"JSON Message"}'), $this->debug($output));
    }

    public function testConsumerForAllMessages() {
        $count = 10;
        $this->produceMessages($count, "test.one");
        $this->produceMessages($count, "test.two");
        $this->produceMessages($count, "test.three");
        $output = $this->consumeMessages("test_all_messages", $count*3);
        $this->assertSame($count, $this->countString($output, "Routing Key:test.one"), $this->debug($output));
        $this->assertSame($count, $this->countString($output, "Routing Key:test.two"), $this->debug($output));
        $this->assertSame($count, $this->countString($output, "Routing Key:test.three"), $this->debug($output));
    }

    public function testConsumerForAllMessagesWithPattern() {
        $count = 10;
        $this->produceMessages($count, "test.one");
        $this->produceMessages($count, "test.two");
        $this->produceMessages($count, "test.three");
        $this->produceMessages($count, "not.test.four");
        $output = $this->consumeMessages("test_all_messages_with_pattern", $count*4);
        $this->assertSame($count, $this->countString($output, "Routing Key:test.one"), $this->debug($output));
        $this->assertSame($count, $this->countString($output, "Routing Key:test.two"), $this->debug($output));
        $this->assertSame($count, $this->countString($output, "Routing Key:test.three"), $this->debug($output));
        $this->assertSame(0, $this->countString($output, "Routing Key:not.test.four"), $this->debug($output));
    }

    public function testDirectConsumer() {
        $count = 10;
        $this->produceMessages($count, "test.direct", null, "test_direct_producer");
        $output = $this->consumeMessages("test_direct", $count);
        $this->assertSame($count, $this->countString($output, "Routing Key:test.direct"), $this->debug($output));
    }

    public function testFanoutConsumer() {
        $count = 10;
        $this->produceMessages($count, "", null, "test_fanout_producer");
        $output = $this->consumeMessages("test_one_fanout", $count);
        $this->assertSame($count, $this->countString($output, "Routing Key:"), $this->debug($output));
        $output = $this->consumeMessages("test_two_fanout", $count);
        $this->assertSame($count, $this->countString($output, "Routing Key:"), $this->debug($output));
    }

    public function testMultipleConsumers() {
        $count = 10;
        $this->produceMessages($count, "test.one");
        $this->produceMessages($count, "test.three");
        $output = $this->consumeMessages("test_multiple_consumers", $count * 2);
        $this->assertSame($count, $this->countString($output, "Routing Key:test.one"), $this->debug($output));
        $this->assertSame($count, $this->countString($output, "Routing Key:test.three"), $this->debug($output));
    }

    public function testConsumerThatThrowsException() {
        $count = 20;
        $this->produceMessages($count, "test.three");
        $output = $this->consumeMessages("test_exception_consumer", $count);
        $this->assertTrue( 0 < $this->countString($output, "Routing Key:test.three"), $this->debug($output));
        $this->assertTrue($this->has($output, "exception"), $this->debug($output));
    }

    public function testRejectConsumer() {
        $count = 1;
        $this->produceMessages($count, "test.four");
        $output = $this->consumeMessages("test_reject", $count * 5);
        $this->assertSame($count, $this->countString($output, "Routing Key:test.four"), $this->debug($output));
    }

    public function testRejectRequeueStopConsumer() {
        $count = 2;
        $this->produceMessages($count, "test.three");
        $output = $this->consumeMessages("test_reject_requeue_stop", $count);
        $this->assertSame(1, $this->countString($output, "Routing Key:test.three"), $this->debug($output));
    }
    /**
     * @TODO: Test Not Working
     */
    public function XtestConsumeWithSerializationEncoder() {
        $phpObject = new \stdClass();
        $phpObject->message = "PHP Object Message";
        $this->produceMessages(1, "test.one", $phpObject, "test_php_object_encoder");
        $output = $this->consumeMessages("test_php_object_decoder", 1);
        var_dump($output);
        //$this->assertTrue($this->has($output, 'O:8:"stdClass":1:{s:7:"message";s:18:"PHP Object Message";}'), $this->debug($output));
    }

    /**
     * @TODO: Test Not Working
     * - Published message goes to the queue but not redelivered to this consumer.
     */
    public function XtestRepublishConsumer() {
        $count = 1;
        $this->produceMessages($count, "test.one");
        $output = $this->consumeMessages("test_republish", $count*10);
        var_dump($output);
        //$this->assertTrue($count == $this->countString($output, "Routing Key:test.one"), $this->debug($output));
    }
}
