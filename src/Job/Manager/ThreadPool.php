<?php

namespace Legume\Job\Manager;

use Legume\Job\ManagerInterface;
use Legume\Job\QueueAdaptorInterface;
use Legume\Job\Stackable;
use Legume\Job\Worker\ThreadWorker;
use Pool;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Threaded;

class ThreadPool extends Pool implements ManagerInterface
{
    /** @var QueueAdaptorInterface $adaptor */
    protected $adaptor;

    /** @var LoggerInterface $log */
    protected $log;

    /** @var boolean $running */
    protected $running;

    /** @var int $startTime */
    protected $startTime;

    /** @var Threaded[int] */
    protected $work;

	/** @var ThreadWorker[int] */
	protected $workers;

    /** @var int $last */
	protected $last;

	/**
	 * @param QueueAdaptorInterface $adaptor
	 * @param string $autoload
	 */
    public function __construct(QueueAdaptorInterface $adaptor, $autoload)
    {
        parent::__construct(1, ThreadWorker::class, array($autoload));

        $this->adaptor = $adaptor;
		$this->log = new NullLogger();
        $this->running = false;
        $this->work = array();

        $this->workers = array();
        $this->last = 0;
    }

    /**
     * @inheritdoc
     */
	public function shutdown()
	{
		$this->stop();
		$this->collect();

		// Cleanup the workers and unstack jobs.
		parent::shutdown();
	}

	public function stop()
	{
		$this->running = false;
	}

    /**
     * @inheritdoc
     */
    public function submit($task)
    {
		$next = 0;
		if ($this->size > 0) {
			$next = ($this->last + 1) % $this->size;
			foreach ($this->workers as $i => $worker) {
				if (isset($this->workers[$next]) && $worker->getStacked() < $this->workers[$next]->getStacked()) {
					$next = $i;
				}
			}
		}

		if (!isset($this->workers[$next])) {
			$this->workers[$next] = new $this->class(...$this->ctor);
			$this->workers[$next]->setLogger($this->log);
			$this->workers[$next]->start();
		}

		return $this->submitTo($next, $task);
    }

    /**
     * @inheritdoc
     */
    public function submitTo($worker, $task)
    {
        if (!isset($this->workers[$worker])) {
            throw new RuntimeException("The selected worker ({$worker}) does not exist!");
        }

        $this->last = $worker;
        $this->work[] = $task;
        return $this->workers[$worker]->stack($task);
    }

    /**
     * @inheritdoc
     */
    public function collect($collector = null)
    {
        if ($collector == null) {
            $collector = array($this, "collector");
        }

        foreach ($this->work as $i => $task) {
            if (call_user_func($collector, $task)) {
                unset($this->work[$i]);
            }
        }

		// If there is no more work, clean-up works.
		if (count($this->work) < 1 && count($this->workers) > 0) {
			$this->log->debug("Checking " . count($this->workers) . " worker(s) for idle.");

			foreach ($this->workers as $i => $worker) {
				$stacked = $worker->getStacked();
				if ($stacked < 1) {
					if (! $worker->isShutdown()) {
						$this->log->info("Shutting down worker {$i} due to idle.");
						$worker->shutdown();
					} else if ($worker->isJoined()) {
						$this->log->info("Cleaning up worker {$i}.");
						unset($this->workers[$i]);
					}
				}
			}
		}

        return count($this->work);
    }

    /**
     * @param Stackable $work
     *
     * @return bool
     */
    protected function collector(Stackable $work)
    {
        $complete = $work->isComplete();
        if ($complete) {
            if ($work->isTerminated()) {
                $this->log->warning("Job {$work->getId()} failed and will be submitted for retry!");
                $this->adaptor->retry($work);
            } else {
                $this->log->info("Job {$work->getId()} completed successfully.");
                $this->adaptor->complete($work);
            }
        } else if (!$this->running) {
			$this->log->debug("Returning job {$work->getId()} to the queue.");
			$this->adaptor->retry($work);
		} else {
            $this->log->debug("Requesting more time for job {$work->getId()}.");
            $this->adaptor->touch($work);
        }

        return $complete;
    }

	/**
	 * @inheritdoc
	 */
	public function run()
	{
		$this->running = true;
		$this->startTime = time();

		while ($this->running) {
			// Don't pick up new jobs if the avarge stack size is larger than 1/2 the pool size.
			if ($this->size > 0 && count($this->work) / $this->size < $this->size / 2) {
				$stackable = $this->adaptor->listen(5);

				if ($stackable !== null) {
					$this->log->info("Pool received new job: {$stackable->getId()}");

					try {
						$this->submit($stackable);
					} catch (RuntimeException $e) {
						$this->log->warning($e->getMessage());
					}
				}
			} else {
				sleep(5);
			}

			$this->collect();
		}
	}

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->log = $logger;
    }
}
