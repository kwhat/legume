<?php

namespace Legume\Job\Worker;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Worker;

class ThreadWorker extends Worker
{
    /** @var string $autoload */
    protected $autoload;

    /** @var int $jobCount */
    protected $jobCount;

    /** @var int $startTime */
    protected $startTime;

    /**
     * @param string $autoload
     */
    public function __construct($autoload)
    {
        $this->autoload = $autoload;
		$this->log = new NullLogger();
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        require($this->autoload);
        $this->startTime = time();
    }

    /**
     * Use the inherit none option by default.
     *
     * @inheritdoc
     */
    public function start($options = PTHREADS_INHERIT_NONE)
    {
        return parent::start($options);
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->log = $logger;
    }
}
