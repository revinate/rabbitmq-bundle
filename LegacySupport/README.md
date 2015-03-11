Legacy Support for frameworks older than Symfony 2

Sample Consumer
RabbitMqServiceContainer::getInstance()
    ->getConsumer($consumerName)
    ->consume($prefetchCount);

Sample Producer
RabbitMqServiceContainer::getInstance()
    ->getProducer($producerName)
    ->publish("test message", $routingKey)

How to Setup Exchanges and Queues in RabbitMq
RabbitMqServiceContainer::getInstance()->setup();