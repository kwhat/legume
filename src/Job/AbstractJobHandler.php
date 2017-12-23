<?php

namespace Legume\Job;

use Psr\Container\ContainerInterface as DI;

abstract class AbstractJob implements HandlerInterface
{
    /** @var DI $container */
    private $container;

    /**
     * Handler constructor.
     *
     * @param DI $container
     */
    public function __construct(DI $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    protected function getDependency($key, $default = null)
    {
        $return = $default;
        if ($this->container->has($key)) {
            $return = $this->container->get($key);
        }

        return $return;
    }

    /**
     * @param string $jobId
     * @param string $workload
     */
    abstract public function __invoke($jobId, $workload);
}
