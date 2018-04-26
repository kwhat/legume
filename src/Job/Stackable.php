<?php

namespace Legume\Job;

use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Threaded;

class Stackable extends Threaded implements LoggerAwareInterface
{
    /** @var callable $callable */
    protected $callable;

    /** @var int $id */
    protected $id;

    /** @var LoggerInterface $log */
    protected $log;

    /** @var string $workload */
    protected $workload;

    /** @var boolean $complete */
    protected $complete;

    /**
     * @param $callable $callable
	 * @param int $id
	 * @param string $workload
     */
    public function __construct(callable $callable, $id, $workload)
    {
        $this->callable = $callable;
        $this->id = $id;
		$this->log = new NullLogger();
        $this->workload = $workload;

        $this->complete = false;
    }

    public function run()
    {
        try {
            // The dependency injector currently owns the callback, synchronize.
            $status = call_user_func($this->callable, $this->id, $this->workload);
        } catch (Exception $e) {
            $this->log->critical($e->getTraceAsString());
			$status = 255;
        }

		//$this->terminated = ($status > 0);
        $this->complete = true;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return "{$this->id}";
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->workload;
    }

    /**
     * Determine whether this Threaded has completed.
     *
     * @return boolean
     */
    public function isComplete()
    {
        return $this->complete;
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
