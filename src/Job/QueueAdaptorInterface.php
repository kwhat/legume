<?php

namespace Legume\Job;

use Psr\Container\ContainerInterface as DI;
use Psr\Log\LoggerAwareInterface;

interface QueueAdaptorInterface extends LoggerAwareInterface
{
    /**
     * @param DI $container
     */
    public function __construct(DI $container);
    
    /**
     * @param int|null $timeout
     * 
     * @return Stackable|null
     */
    public function listen($timeout = null);

    /**
     * @param Stackable $work
     * @return bool
     */
    public function touch(Stackable $work);

    /**
     * @param Stackable $work
     */
    public function complete(Stackable $work);

    /**
     * @param Stackable $work
     */
    public function retry(Stackable $work);
}
