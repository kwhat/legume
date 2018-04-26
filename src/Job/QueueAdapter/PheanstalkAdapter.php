<?php

namespace Legume\Job\QueueAdapter;

use Legume\Job\HandlerInterface;
use Legume\Job\QueueAdaptorInterface;
use Legume\Job\Stackable;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Psr\Container\ContainerInterface as DI;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PheanstalkAdapter implements QueueAdaptorInterface
{
    /** @var Pheanstalk $client */
    protected $client;

    /** @var DI $container */
    protected $container;

	/** @var callable[string] $jobs */
	protected $jobs;

    /** @var LoggerInterface $log */
    protected $log;

	/**
	 * @param DI $container
	 */
    public function __construct(DI $container)
    {
        $this->client = $container->get(Pheanstalk::class);
        $this->container = $container;
        $this->jobs = array();
		$this->log = new NullLogger();
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
	 * @inheritdoc
	 */
    public function register($name, $callback)
    {
		$this->jobs[$name] = $callback;
        $this->client->watch($name);
    }

	/**
	 * @inheritdoc
	 */
    public function unregister($name)
    {
        $this->client->ignore($name);
        unset($this->jobs[$name]);
    }

	/**
	 * @inheritdoc
	 */
    public function listen($timeout = null)
    {
        $stackable = null;

        $job = $this->client->reserve($timeout);
        if ($job !== false) {
            $info = $this->client->statsJob($job);
            $tube = $info["tube"];

            if (isset($this->jobs[$tube])) {
            	$callable = $this->jobs[$tube];

            	if (is_string($callable) && in_array(HandlerInterface::class, class_implements($callable, true))) {
					/** @var HandlerInterface $callable */
            		$callable = new $callable();
					$callable->setLogger($this->log);
				}

				if (is_callable($callable)) {
            		$stackable = new Stackable(
						$callable,
						$job->getId(),
						$job->getData()
					);
				} else {
            		$this->log->warning("Failed to locate callable for job '{$tube}'!");
				}
            } else {
				$this->log->warning("No job registered for '{$tube}'!");
			}
        }

        return $stackable;
    }

	/**
	 * @inheritdoc
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
	 * @inheritdoc
	 */
    public function setLogger(LoggerInterface $logger)
    {
        $this->log = $logger;
    }
}
