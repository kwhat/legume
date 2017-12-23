<?php

namespace Legume\Job;

use Psr\Container\ContainerInterface as DI;
use Psr\Log\LoggerAwareInterface;

interface HandlerInterface extends LoggerAwareInterface
{
    /**
     * Default constructor.
     *
     * @param DI $container
     */
    public function __construct(DI $container);

    /**
     * Dispatcher callback for this job handler.
     *
     * @param string $jobId
     * @param string $workload
     */
    public function __invoke($jobId, $workload);
}
