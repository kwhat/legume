<?php

namespace Legume\Job\QueueAdapter;

use Legume\Job\QueueAdaptorInterface;
use Legume\Job\Stackable;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Psr\Container\ContainerInterface as DI;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

class PheanstalkAdapter implements QueueAdaptorInterface
{
    /** @var DI $container */
    private $container;

    /** @var Pheanstalk $client */
    protected $client;

    /** @var LoggerInterface $log */
    protected $log;

    /**
     * @param DI $container Required for finding jobs.
     */
    public function __construct(DI $container)
    {
        $this->client = $container->get(Pheanstalk::class);
        $this->container = $container;

        $this->log = new Logger(basename($_SERVER["SCRIPT_FILENAME"], ".php"));
        $this->log->pushHandler(new NullHandler(Logger::DEBUG));
    }

    /**
     * Make sure we close the connection to beanstalk
     */
    public function __destruct()
    {
        if (isset($this->client)) {
            $this->client->getConnection()->disconnect();
        }
    }

    /**
     * @param string $callback
     */
    public function register($callback)
    {
        $this->client->watch(str_replace("\\", "/", $callback));
    }

    /**
     * @param string $callback
     */
    public function unregister($callback)
    {
        $this->client->ignore(str_replace("\\", "/", $callback));
    }

    /**
     * @param int|null $timeout
     *
     * @return Stackable|null
     */
    public function listen($timeout = null)
    {
        $stackable = null;

        $job = $this->client->reserve($timeout);
        if ($job !== false) {
            $info = $this->client->statsJob($job);
            $tube = str_replace("/", "\\", $info["tube"]);

            if ($this->container->has($tube)) {
                $stackable = new Stackable(
                    $this->container->get($tube),
                    $job->getId(),
                    $job->getData()
                );
            }
        }

        return $stackable;
    }

    /**
     * @param Stackable $work
     *
     * @return boolean;
     */
    public function touch(Stackable $work)
    {
        $job = new Job($work->getId(), $work->getData());

        $this->client->touch($job);
    }

    /**
     * @inheritdoc
     */
    public function complete(Stackable $work)
    {
        $job = new Job($work->getId(), $work->getData());

        $this->client->delete($job);
    }

    /**
     * @inheritdoc
     */
    public function retry(Stackable $work)
    {
        $job = new Job($work->getId(), $work->getData());

        $this->client->release($job);
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->log = $logger;
    }
}
