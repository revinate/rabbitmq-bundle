<?php
namespace Revinate\RabbitMqBundle\Test\TestCase;

use PhpAmqpLib\Exception\AMQPTimeoutException;
use Revinate\RabbitMqBundle\Consumer\RPCConsumer;
use Revinate\RabbitMqBundle\Message\Message;
use Revinate\RabbitMqBundle\Producer\Producer;
use Revinate\RabbitMqBundle\Test\Message\CustomMessage;
use Revinate\RabbitMqBundle\Test\TestCase\BaseTestCase;

class ConsumerProducerTest extends BaseTestCase
{

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

    protected function setUp() {
        $this->create();
    }

    protected function tearDown() {
        $this->clear();
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

    public function testBulkConsumerWithBufferWait() {
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

    public function testMultipleConsumersAlt() {
        $count = 10;
        $this->produceMessages($count, "test.one");
        $this->produceMessages($count, "test.three");
        $output = $this->consumeMessages("test_multiple_consumers_alt", $count * 2);
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
        $this->produceMessages($count, "test.zero");
        $output = $this->consumeMessages("test_reject_requeue_stop", $count);
        $this->assertSame(1, $this->countString($output, "Routing Key:test.zero"), $this->debug($output));
    }

    public function testRejectStopConsumer() {
        $count = 2;
        $this->produceMessages($count, "test.zero");
        $output = $this->consumeMessages("test_reject_drop_stop", $count);
        $this->assertSame(1, $this->countString($output, "Routing Key:test.zero"), $this->debug($output));
    }

    public function testPublishToSelf() {
        $this->produceMessages(1, "test.six");
        $output = $this->consumeMessages("test_self_publish", 6);
        $this->assertSame(1, $this->countString($output, "Routing Key:test.six"), $this->debug($output));
        // Self published messages
        $this->assertSame(5, $this->countString($output, "Routing Key:test_six_q"), $this->debug($output));
    }

    public function testConsumeWithSerializationEncoder() {
        $phpObject = new \stdClass();
        $phpObject->message = "PHP Object Message";
        $this->produceMessages(1, "test.seven", $phpObject, "test_php_object_encoder");
        $output = $this->consumeMessages("test_php_object_decoder", 1);
        $this->assertTrue($this->has($output, 'O:8:"stdClass":1:{s:7:"message";s:18:"PHP Object Message";}'), $this->debug($output));
    }

    public function testRPCPublisherConsumer() {
        /** @var Producer $producer */
        $producer = $this->getContainer()->get("revinate_rabbit_mq.producer.test_producer");
        $rpcConsumer = new RPCConsumer($producer);
        // Client Request
        $queue = $rpcConsumer->call("RPC Message", "rpc.message");
        // Server Reply
        $output = $this->consumeMessages("test_rpc_server", 1);
        // Client Consumegs
        $rpcConsumer->consume($queue, 5, function(Message $message) use($output) {
            $this->assertTrue($this->has($message->getRoutingKey(), "rpc_"), $this->debug($output));
        });
    }

    /**
     * @TODO: Test Not Working
     * - Published message goes to the queue but not redelivered to this consumer.
     */
    public function xtestRepublishConsumer() {
        $count = 1;
        $this->produceMessages($count, "test.eight");
        $output = $this->consumeMessages("test_republish", $count*10);
        var_dump($output);
        //$this->assertTrue($count == $this->countString($output, "Routing Key:test.one"), $this->debug($output));
    }

    public function testDeadletterMessageFromConsumer() {
        $count = 10;
        $this->produceMessages($count, "test.nine", "Deadlettered Message");
        $output = $this->consumeMessages("test_nine", $count);
        $this->assertTrue($this->has($output, "Deadlettered Message"), $this->debug($output));

        // Consume messages from dlx queue and check error header
        $output = $this->consumeMessages("test_dlx", $count);
        $this->assertTrue($count == $this->countString($output, "Error: Something went wrong. Please try again!"), $this->debug($output));
    }

    /**
     * Test the reject drop and stop with error exception
     *
     * Here we produce 5 messages and then we try and consume them.
     * Ideally only 1 message will be consumed and we verify that by checking dlx.
     * The message int eh dlx also should contain the one message with the error specified in the consumer
     */
    public function testRejectDropStopWithError() {
        $count = 5;
        $this->produceMessages($count, "test.ten", "Message is here!");
        $output = $this->consumeMessages("test_ten", $count);
        $this->assertTrue($this->has($output, "Message is here!"), $this->debug($output));

        $output = $this->consumeMessages("test_dlx", $count);
        $this->assertNotFalse(strpos($output, "Somethings wrong! Consuming has stopped! Here is why!?"));
        $this->assertEquals(1, substr_count($output, "Somethings wrong! Consuming has stopped! Here is why!?"));
    }

    public function testBulkConsumerWithBufferWaitAndQueueSizeLessThanBatchSize() {
        // batch_size is 10
        $count = 5;
        $this->produceMessages($count, "test.eleven");
        $output = $this->consumeMessages("test_eleven", $count);
        $this->assertSame($count, $this->countString($output, "Routing Key:test.eleven"), $this->debug($output));
        $this->assertTrue($this->countString($output, "Returning from Bulk execute") > 1, $this->debug($output));
    }

    public function testBaseProducerAndConsumer() {
        $producer = $this->getContainer()->get("revinate.rabbit_mq.base_producer");
        $exchange = $this->getContainer()->get("revinate_rabbit_mq.exchange.test_topic.tx");
        $producer->setExchange($exchange);
        $producer->publish("Base Producer test", "test.zero");
        $output = $this->consumeMessages("test_zero", 1);
        $this->assertTrue($this->has($output, "Routing Key:test.zero"), $this->debug($output));
    }

    public function testConsumingAfterTimeout() {
        $producer = $this->getContainer()->get("revinate_rabbit_mq.producer.test_producer");
        $consumer = $this->getContainer()->get("revinate_rabbit_mq.consumer.test_one");
        try {
            $consumer->consume(1);
        } catch(AMQPTimeoutException $e) {
            // Caught exception
        }
        $producer->publish("test message", "test.one");
        ob_start();
        $consumer->consume(1);
        $output = ob_get_clean();
        $this->assertTrue($this->has($output, "Routing Key:test.one"), $this->debug($output));
    }

    public function testConsumeMessageHeaderValueTypes() {
        $count = 1;
        $now = new \DateTime();
        $headers = array(
            "stringHeader1" => array("S", "this_is_string_1"),
            "stringHeader2" => "this_is_string_2",
            "intHeader" => 8,
            "floatHeader" => 3.14,
            "boolHeader" => true,
            "nullHeader" => null,
            "dateTimeHeader" => $now,
            "arrayHeader" => array("string1", "string2")
        );
        $message = new Message("data", "test.ten", $headers);
        $this->produceMessages($count, "test.ten", $message);
        $output = $this->consumeMessages("test_ten", $count);
        $this->assertSame($count, $this->countString($output, 'Header: stringHeader1 = ["S","this_is_string_1"]'), $this->debug($output));
        $this->assertSame($count, $this->countString($output, 'Header: stringHeader2 = ["S","this_is_string_2"]'), $this->debug($output));
        $this->assertSame($count, $this->countString($output, 'Header: intHeader = ["I",8]'), $this->debug($output));
        $this->assertSame($count, $this->countString($output, 'Header: floatHeader = ["S","3.14"]'), $this->debug($output));
        $this->assertSame($count, $this->countString($output, 'Header: boolHeader = ["t",true]'), $this->debug($output));
        $this->assertSame($count, $this->countString($output, 'Header: nullHeader = ["V",null]'), $this->debug($output));
        $this->assertSame($count, $this->countString($output, 'Header: dateTimeHeader = ["T",' . $now->getTimestamp() . ']'), $this->debug($output));
        $this->assertSame($count, $this->countString($output, 'Header: arrayHeader = ["A",'), $this->debug($output));
    }
}
