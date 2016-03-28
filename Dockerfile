FROM registry.revinate.net/appdev/revinate-composer:1.1.1

RUN composer global require "phpunit/phpunit=4.8.*"
RUN composer global require "phpunit/php-invoker=~1.1."

ADD . /app

ENTRYPOINT "/app/entrypoint.sh"
