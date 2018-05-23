#/bin/bash

.PHONY: tests
tests:
	make build
	docker-compose up -d rabbitmq
	sleep 5
	docker-compose run rabbitmq-bundle
	docker-compose down
	docker rmi rabbitmqbundle_rabbitmq-bundle

.PHONY: build
build:
	docker-compose build --force-rm
