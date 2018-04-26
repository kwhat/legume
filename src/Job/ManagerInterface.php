<?php

namespace Legume\Job;

use Psr\Log\LoggerAwareInterface;

interface ManagerInterface extends LoggerAwareInterface
{
	/**
	 * Manager constructor.
	 *
	 * @param QueueAdaptorInterface $adaptor
	 * @param string $autoload
	 */
    public function __construct(QueueAdaptorInterface $adaptor, $autoload);

    /**
     * A Callable collector that returns a boolean on whether the task can be collected
     * or not. Only in rare cases should a custom collector need to be used.
     *
     * @param callable|null $collector
     *
     * @return int
     */
    public function collect($collector = null);

	/**
	 * @inheritDoc
	 */
    public function shutdown();

	public function stop();

	/**
	 * Runloop for this worker process.
	 */
	public function run();
}
