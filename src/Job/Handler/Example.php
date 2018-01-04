<?php

namespace Legume\Job\Handler;

use Legume\Job\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface as DI;
use Psr\Log\LoggerInterface;

class Example implements HandlerInterface
{
    // Setup an optional time limit for this job.
    const TIMEOUT = 120;

    /** @var LoggerInterface $log */
    protected $log;

    public function __construct(DI $container)
    {
        $this->log = new Logger(basename($_SERVER["SCRIPT_FILENAME"], ".php"));
        $this->log->pushHandler(new NullHandler(Logger::DEBUG));
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
