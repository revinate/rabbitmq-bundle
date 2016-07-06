[![Build Status](https://travis-ci.org/revinate/rabbitmq-bundle.svg?branch=master)](https://travis-ci.org/revinate/rabbitmq-bundle)

## About this bundle
This bundle is inspired from [oldSoundRabbitMqBundle](https://github.com/videlalvaro/RabbitMqBundle) and  will help you to build asynchronous services using RabbitMq message broker.

## How to install
1. Install [Composer] (http://symfony.com/doc/current/cookbook/composer.html)
2. Run `composer require revinate/rabbitmq-bundle` and then `composer update revinate/rabbitmq-bundle`
3. It is wise to anchor the version to latest tag rather than "dev-master"

## Before you use this bundle, read up on:
1. [RabbitMq Concepts](http://www.rabbitmq.com/resources/google-tech-talk-final/alexis-google-rabbitmq-talk.pdf)
2. [Some other resources] (https://www.rabbitmq.com/how.html#quickstart) \(Optional\)

***

### How to use this Bundle

1. `brew install rabbitmq ` to [install RabbitMq](https://www.rabbitmq.com/install-homebrew.html)

2. Open `app/AppKernel.php` and add following line to `registerBundles()` method
`new Revinate\RabbitMqBundle\RevinateRabbitMqBundle()`

3. Configure rabbitMq Connection config in `config_dev.yml`, `config_prod.yml` and `config_test.yml`.
    ```yaml
    revinate_rabbit_mq:
        connections:
            myConnection:
                user:     'myuser'
                password: 'mypassword'
                host:     '127.0.0.1'
                vhost:    '/default'
    ```

4. Create a new config called `rabbitmq.yml` in `app/config` directory and add the import to `app/config/config.yml`:
    ```yaml
    imports:
        - { resource: rabbitmq.yml }
    ```

5. Now setup Exchanges, Queues, Consumers and Producers. If you are not sure what those terms mean, please go back to reading material given above :smiley:

    Here is a sample rabbitmq.yml file
    ```yaml
    revinate_rabbit_mq:

        exchanges:
            log.tx: { connection: myConnection }
            events.tx: { connection: myConnection }

        queues:
            error_log_q: { exchange: log.tx, routing_keys: ['error.log'] }

        producers:
            error_log: { exchange: log.tx }

        consumers:
            error_log: { queue: error_log_q,  callback: revinate.rabbitmq.LogEventConsumer, batch_size: 10, buffer_wait: 400, idle_timeout: 2 }
    ```

    **Setup**: Run this command `revinate:rabbitmq:setup` to create exchanges and queues in rabbitmq cluster. You can check `127.0.0.1:15672` to view created exchanges and queues.

    * **Exchanges**: Exchanges array defines all exchanges to be used. Here are the various properties an exchange can be defined with:
        * **_connection_**: Connection to use. \(Required\)
        * _type_: Can be `direct`, `fanout` or `topic`. Default: `topic`
        * _passive_: If set server raises an error if exchange is not declared. Default: `false`.
        * _managed_: If false, exchange is not managed by this bundle. Default: `true` . Set this option to `false` if you are not responsible to create/delete this exchange.
        * _durable_: Durable exchanges remain active when a server restarts. Default: `true`
        * _auto_delete_: If set, the exchange is deleted when all queues have finished using it. Default: `false`
        * _internal_: If set, the exchange may not be used directly by publishers, but only when bound to other exchanges.  Default: `false`
        * _nowait_: If set, the server will not respond to declare method. Default: `false`

    * **Queues:** Queues array defines all queues to be used. Here are the various properties a queue can be defined with:
        * **_exchange_**: Exchange to which this queue is bound to. \(Required\)
        * **_routing_keys_**: Routing keys to which this queue should be bound to. Default: `#`. 
        * _passive_: If set server raises an error if queue is not declared. Default: `false`.
        * _exclusive_: Exclusive queues may only be accessed by the current connection, and are deleted when that connection closes. Default: `false`
        * _managed_: If false, queue is not managed by this bundle. Default: `true` . Set this option to `false` if you are not responsible to create/delete this queue.
        * _durable_: Durable queues remain active when a server restarts. Default: `true`
        * _auto_delete_: If set, the queue is deleted when all consumers have finished using it. Default: `false`
        * _nowait_: If set, the server will not respond to declare method. Default: `false`
        * _arguments_: Queue specific arguments. Read up on [Per Queue TTL](https://www.rabbitmq.com/ttl.html#per-queue-message-ttl) and [Deadletter Exchanges](https://www.rabbitmq.com/dlx.html) to checkout possible arguments.

    * **Producers:** Producers array defines all producers. Here are the various properties a producer can be defined with:
        * **_exchange_**: Exchange to which this producer will send message to. \(Required\)
        * _encoder_: Encoder service to use to encode messages. Default: `revinate.rabbit_mq.encoder.json`
            * You can define your own encoder by creating a service that implements `EncoderInterface`

    * **Consumers:** Consumers array defines all message consumers. Here are the various properties a consumer can be defined with:
        * **_queue_**: Queue from which this consumer should consume from. \(Required\)
	    * **_queues_**: Instead of a single queue, a consumer can consume from multiple queues. This can be an array of queues. Either `queue` or `queues` needs to be set.
        * **_callback_**: Callback service to call when message arrives. \(Required\)
	    *  **_callbacks_**: If you have set `queues`, you need to setup callback for each of the queues. This can be an array of callbacks.
            * Callback service should either implement `ConsumerInterface` or `BatchConsumerInterface`. 
            * Callback service can implement `ContainerAwareInterface` in order to get access to Container.
        * _idle_timeout_: If there is nothing in queue, consumer will quit in these many seconds. Default: `0` which means never.
        * _qos_options_: Following qos options are supported. [Great post on QO] Options(http://www.rabbitmq.com/blog/2012/05/11/some-queuing-theory-throughput-latency-and-bandwidth/)
            * _prefetch_size_: Size limit of messages to prefetch. Default: `0` or no limit
            * _prefetch_count_: Number of messages to prefetch. Default: `0` or no limit
	            * Note that for `batch consumer`, `prefetch_count` is overridden to consumer `target` size in order to avoid certain unexpected batch processing behavior.
            * _global_: If set to true, these settings are shared by all consumers. Default: `false`
        * _batch_size_: Number of messages to process in bulk. If you use this option, your consumer callback should implement `BatchConsumerInterface` which accepts a batch of messages at a time. You can ack/nack these messages in one go. Useful if you want to do bulk operations on a set of messages. Default: `null` or 1.
            * If `batch_size` is set, `qos:prefetch_count` is set to `batch_size`
            * When batch consumer starts, the batch size starts from 1 and doubles every tick to reach `batch_size`. This is done in order to avoid cases when queue has messages less than the `batch_size`.
        * _buffer_wait_: Number of milliseconds to wait for buffer to get full before flushing. This should be set carefully. It should small enough that your consumer is not waiting for buffer to get full and large enough that you are processing `batch_size` number of messages. Default: `1000ms`. 
        * _message_class_: Custom Message class. You can extend `Message` class to define your own message class to use. Default: `Message`
        * _decoder_: Decoder service to use to decode messages. Default: `revinate.rabbit_mq.decoder.json`
            * You can define your own decoder by creating a service that implements `DecoderInterface`
          

6. **Produce Messages**
    * You can access your defined producers using `revinate_rabbit_mq.producer.<producer_name>` service syntax. 
    * Example: `error_log` producer defined above can be accessed as following:

        ```php
           $producer = $this->getContainer()->get('revinate_rabbit_mq.producer.error_log')
        ```
    * Publish Messages:  

         ```php
         $producer->publish("This is a log", "error.log");
         ```
    * Republish Messages:  

         ```php
         $producer->rePublish(Revinate\RabbitMqBundle\Message\Message $message);
         ```
    * Dynamic Producer:
        * You can get a dynamic producer which can publish to multiple exchanges like this:

            ```php
            $producer = $this->getContainer()->get('revinate.rabbit_mq.base_producer');
            $exchange1 = $this->getContainer()->get('revinate.rabbit_mq.exchange.log.tx'); 
            $exchange2 = $this->getContainer()->get('revinate.rabbit_mq.exchange.events.tx'); 
            // Set Exchange
            $producer->setExchange($exchange1);
            $producer->publish("log", "error.log");
            // Switch Exchange
            $producer->setExchange($exchange2);
            $producer->publish("event", "error.event");
            ```


7. **Consume Messages**
    * Callback service should either implement `ConsumerInterface` or `BatchConsumerInterface`. 
    * Callback service can implement `ContainerAwareInterface` in order to get access to Container.
    * Consumer Example:

        ```php
        class LogEventConsumer implements ConsumerInterface, ContainerAwareInterface {
            /** @var  ContainerInterface */
            protected $container;
        
            /**
             * @param \Revinate\RabbitMqBundle\Message\Message $message
             * @return int[]
             */
            public function execute($message) {
                print_r($message->getData());
                return DeliveryResponse::MSG_ACK;;
            }
        }
        ```

    * Batch Consumer Example:

        ```php
        class LogEventConsumer implements BatchConsumerInterface, ContainerAwareInterface {
            /** @var  ContainerInterface */
            protected $container;
        
            /**
             * @param \Revinate\RabbitMqBundle\Message\Message[] $messages
             * @return int[]
             */
            public function execute($messages) {
                $statuses = array();
                foreach ($messages as $message) {
                    print_r($message->getData());
                    $statuses[] = DeliveryResponse::MSG_ACK;
        
                }
                return $statuses;
            }
        
            /**
             * @param ContainerInterface $container
             */
            public function setContainer(ContainerInterface $container = null) {
                $this->container = $container;
            }
        }
        ```
8. **RPC**
    ```php
        // Producer that publishes to the exchange server is listening on 
        $producer = $this->getContainer()->get("revinate_rabbit_mq.producer.test_producer");
        $rpcConsumer = new RPCConsumer($producer);

        // Client Request with message data and routing key that server listens on
        $queue = $rpcConsumer->call("RPC Message", "rpc.message");

        // Server Reply (Done by Server)

        // Client Consume which blocks until timeout to receive response 
        $rpcConsumer->consume($queue, 5 /* timeout */, function(Message $message) {
            // Process Response
            var_dump($message->getData());
        });
    ```


### Legacy Support for < Symfony 2
1. Create a rabbitmq.yml file anywhere in your codebase. Here is a sample file:

    ```yaml
    revinate_rabbit_mq:
        connections:
            app:
                user:     'myuser'
                port:     5672
                vhost:    '/myhost'
                lazy:     true
    
        environment:
            prod:
                connections:
                    app:
                        password: 'mypassword'
                        host:     'prod.myrabbithost.net'
            test:
                connections:
                    app:
                        password: 'mypassword'
                        host:     'test.myrabbithost.net'
            dev-local:
                connections:
                    app:
                        password: 'mypassword'
                        host:     '127.0.0.1'
    
        exchanges:
            log.tx: { connection: app }
    
        queues:
            error_log_q: { exchange: log.tx, routing_keys: ['error.log'] }
    
        producers:
            error_log: { exchange: log.tx }
    
        consumers:
            error_log: { queue: error_log_q,  callback: LogEventConsumer, batch_size: 10, buffer_wait: 400, idle_timeout: 2 }
    ```

2. Setup Exchanges, Queues
	```
	php setup.php dev-local
	``` 

3. Create a service container class called `RabbitMqServiceContainer` which will act like a `Container` from Symfony2

   ```php
       class RabbitMqServiceContainer {
           public static function getInstance() {
               return ServiceContainer::getInstance(sfConfig::get('sf_environment'),  __DIR__ . "/rabbitmq.yml");
           }
       }
   ```
    
4. Producing Messages
  ```php
   $producer = RabbitMqServiceContainer::getInstance()->getProducer("error_log");
   $producer->publish("This is a log", 'error.log');
   ```

5. Consuming Messages
   * You can write a single cli script which can take a consumer name and work for all consumers in your config.
   * Consumer command line script:

     ```php
       // $consumerName and $prefetchCount can be taken as command line parameters
       $consumer = RabbitMqServiceContainer::getInstance()->getConsumer($consumerName);
       try {
           $consumer->consume($prefetchCount);
       } catch (PhpAmqpLib\Exception\AMQPTimeoutException $e) {
           ;
       }
      ```
    * A consumer callback is implemented the same way it is done for Symfony2.
