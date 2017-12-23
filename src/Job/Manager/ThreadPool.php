<?php

namespace Legume\Job\Manager;

use Closure;
use Legume\Job\ManagerInterface;
use Legume\Job\QueueAdaptorInterface;
use Legume\Job\Stackable;
use Legume\Job\Worker\ThreadWorker;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Pool;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Threaded;

class ThreadPool extends Pool implements LoggerAwareInterface, ManagerInterface
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

    /**
     * @param QueueAdaptorInterface $adaptor
     * @param string $autoload
     */
    public function __construct(QueueAdaptorInterface $adaptor, $autoload)
    {
        parent::__construct(1, ThreadWorker::class, array($autoload));

        $this->adaptor = $adaptor;
        $this->running = false;
        $this->work = array();

        $this->workers = array();
        $this->last = 0;

        $this->log = new Logger(basename($_SERVER["SCRIPT_FILENAME"], ".php"));
        $this->log->pushHandler(new NullHandler(Logger::DEBUG));
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $this->running = true;
        $this->startTime = time();

        while ($this->running) {
            $stackable = $this->adaptor->listen(5);

            if ($stackable !== null) {
                $this->log->debug("Pool received new job: {$stackable->getId()}");

                $this->submit($stackable);
            }

            $this->collect();

            if (count($this->work) < 1) {
                $this->log->debug("Checking " . count($this->workers) . " worker(s) for idle.");

                foreach ($this->workers as $i => $worker) {
                    if ($worker->getStacked() < 1) {
                        if (! $worker->isShutdown()) {
                            $this->log->info("Shutting down worker {$i} due to idle.");
                            $worker->shutdown();
                        } else if ($worker->isJoined()) {
                            $this->log->info("Cleaning up worker {$i}.");
                            //$this->workers = array_slice($this->workers, $i, 1, false);
                            unset($this->workers[$i]);
                        }
                    }
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function shutdown()
    {
        
        echo "SHUTDOWN\n";
        var_dump($this->work);
        foreach ($this->work as $task) {
            if ($task->isRunning()) {
                echo "Task is running {$task->getId()}\n";
            } else echo '... ';
        }


        parent::shutdown();
        $this->running = false;
    }

    /**
     * @inheritdoc
     */
    public function submit($task)
    {
        $next = ($this->last + 1) % $this->size;
        foreach ($this->workers as $i => $worker) {
            if (isset($this->workers[$next]) && $worker->getStacked() < $this->workers[$next]->getStacked()) {
                $next = $i;
            }
        }

        if (! isset($this->workers[$next])) {
            $this->workers[$next] = new $this->class(...$this->ctor);
            $this->workers[$next]->setLogger($this->log);
            $this->workers[$next]->start();
        }

        $last = ($this->last + 1) % $this->size;
        return $this->submitTo($last, $task);
    }

    /**
     * @inheritdoc
     */
    public function submitTo($worker, $task)
    {
        if (! isset($this->workers[$worker])) {
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
                //$this->work = array_slice($this->work, $i, 1, false);
                unset($this->work[$i]);
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
        } else {
            $this->log->debug("Requesting more time for job {$work->getId()}.");
            $this->adaptor->touch($work);
        }

        return $complete;
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
