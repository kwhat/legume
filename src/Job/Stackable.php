<?php

namespace Legume\Job;

use Exception;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Threaded;

class Stackable extends Threaded implements LoggerAwareInterface
{
    /** @var callable $callable */
    protected $callable;

    /** @var int $id */
    protected $id;

    /** @var int $log */
    protected $log;

    /** @var string $workload */
    protected $workload;

    /** @var boolean $complete */
    protected $complete;

    /**
     * @param int $id
     * @param string $workload
     * @param $callable $callable
     */
    public function __construct(callable $callable, $id, $workload)
    {
        $this->callable = $callable;
        $this->id = $id;
        $this->workload = $workload;

        $this->log = new Logger(basename($_SERVER["SCRIPT_FILENAME"], ".php"));
        $this->log->pushHandler(new NullHandler(Logger::DEBUG));

        $this->complete = false;
    }

    public function run()
    {
        if ($this->callable instanceof LoggerAwareInterface) {
            $this->callable->setLogger($this->log);
        }

        try {
            // The dependency injector currently owns the callback, synchronize.
            $status = call_user_func($this->callable, $this->id, $this->workload);
            var_dump($status);
        } catch (Exception $e) {
            var_dump($e);
        }

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
