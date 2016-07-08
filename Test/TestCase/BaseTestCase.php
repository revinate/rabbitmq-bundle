<?php
namespace Revinate\RabbitMqBundle\Test\TestCase;

use AppKernel;
use Revinate\RabbitMqBundle\Command\ConsumerCommand;
use Revinate\RabbitMqBundle\Command\DeleteAllExchangesAndQueuesCommand;
use Revinate\RabbitMqBundle\Command\SetupCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BaseTestCase extends WebTestCase {
    /** @var  AppKernel */
    protected static $kernel;
    /** @var ContainerInterface */
    protected static $container;
    /** @var string ES index prefix */
    protected static $indexPrefix;
    /** @var bool Defining if it's initialize (like some stuff we just need to run once like setting up the mysql schema, es mapping, etc) */
    private static $initialized = false;

    /**
     * Initialize function, which will only be run once
     */
    private static function initialize() {
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        if (! self::$initialized) {
            self::$kernel = new AppKernel('dev', true);
            self::$kernel->boot();
            self::$initialized = true;
        }
    }

    /**
     * @inheritdoc
     */
    public static function setUpBeforeClass() {
        self::initialize();
    }

    /**
     * Get the service container
     *
     * @return ContainerInterface
     */
    public function getContainer() {
        return self::$kernel->getContainer();
    }

    /**
     * Returns a random string of given length
     * @param int   $length  length of random string
     * @return string
     */
    protected static function getRandomString($length) {
        $string = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($string), 0, $length);
    }

    /**
     * @param $commandName
     * @param $commandArguments
     * @return string
     * @throws \Exception
     */
    protected function runCommand($commandName, $commandArguments = array("")) {
        $app = new Application(self::$kernel);
        $app->add(new ConsumerCommand());
        $app->add(new SetupCommand());
        $app->add(new DeleteAllExchangesAndQueuesCommand());
        $app->setAutoExit(false);
        $command = $app->find($commandName);
        $commandString = $command->getName() . " " . implode(" ", $commandArguments);
        $input = new StringInput($commandString);
        $output = new BufferedOutput();
        ob_start();
        $app->run($input, $output);
        $result = ob_get_clean();
        return $result;
    }

    /**
     * @param mixed $results
     * @return string
     */
    protected function debug($results) {
        return "Results: " . print_r($results, true);
    }

    /**
     * @param $payload
     * @param $string
     * @return bool
     */
    protected function has($payload, $string) {
        return strpos($payload, $string) !== false;
    }

    /**
     * @param $payload
     * @param $string
     * @return int
     */
    protected function countString($payload, $string) {
        return substr_count($payload, $string);
    }

}