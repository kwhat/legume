<?php

namespace Legume\Job\Handler;

use Legume\Job\HandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Example implements HandlerInterface
{
    // Setup an optional time limit for this job.
    const TIMEOUT = 120;

    /** @var LoggerInterface $log */
    protected $log;

    public function __construct()
	{
		$this->log = new NullLogger();
	}

	/**
     * @param string $jobId
     * @param string $workload
     *
     * @return int
     */
    public function __invoke($jobId, $workload)
    {
        $this->log->info("{$jobId}: Processing example job for {$workload} seconds...");

        $status = sleep($workload);
        if ($status === false) {
            $status = 1;
        }

        return $status;
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
