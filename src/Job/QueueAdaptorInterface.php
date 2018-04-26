<?php

namespace Legume\Job;

use Psr\Container\ContainerInterface as DI;
use Psr\Log\LoggerAwareInterface;

interface QueueAdaptorInterface extends LoggerAwareInterface
{
	/**
	 * QueueAdaptorInterface
	 */
	public function __construct(DI $container);

	/**
	 * @param string $name
	 * @param callable|string $callback
	 */
	public function register($name, $callback);

	/**
	 * @param string $name
	 */
	public function unregister($name);

    /**
     * @param int|null $timeout
     * 
     * @return Stackable|null
     */
    public function listen($timeout = null);

    /**
     * @param Stackable $work
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
