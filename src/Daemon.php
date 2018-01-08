<?php

namespace Legume;

use Exception;
use GetOpt\GetOpt;
use GetOpt\Option;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class Daemon extends AbstractProcess
{
    /** @var LoggerInterface $log */
    protected $log;

    /** @var Job\ManagerInterface $pool */
    protected $pool;

    public function __construct()
    {
        $this->log = new Logger(basename($_SERVER["SCRIPT_FILENAME"], ".php"));
        $this->log->pushHandler(new SyslogHandler($this->log->getName(), LOG_SYSLOG, Logger::WARNING));

        $suExecOpts = array();
        if (extension_loaded("pcntl")) {
            $suExecOpts[] = Option::create('u', "user", GetOpt::REQUIRED_ARGUMENT)
                ->setDescription("Username to suExec the job manager process");

            $suExecOpts[] = Option::create('g', "group", GetOpt::REQUIRED_ARGUMENT)
                ->setDescription("Groupname to suExec the job manager process");
        }

        if (extension_loaded("pcntl")) {
            $suExecOpts[] = Option::create('n', "nice", GetOpt::REQUIRED_ARGUMENT)
                ->setDescription("The system priority for the job manager process process");
        }

        $daemonOpts = array();
        if (extension_loaded("posix")) {
            $daemonOpts[] = Option::create('D', "daemon")
                ->setDescription("Run the job manager as a background process")
                ->setDefaultValue(false);
        }
    }

    /**
     * @return int
     * @throws Exception
     */
    public function start(Job\ManagerInterface $pool)
    {
        $pid = pcntl_fork();
        switch ($pid) {
            case 0: // Child
                $childPid = posix_setsid();

                $res = pcntl_signal(SIGTERM, array($this, "signal"));
                $res &= pcntl_signal(SIGINT, array($this, "signal"));
                $res &= pcntl_signal(SIGHUP, array($this, "signal"));
                $res &= pcntl_signal(SIGCHLD, array($this, "signal"));
                //$res &= pcntl_signal(SIGALRM, array($this, "signal"));
                //$res &= pcntl_signal(SIGTSTP, array($this, "signal"));
                //$res &= pcntl_signal(SIGCONT, array($this, "signal"));
                if (! $res) {
                    throw new Exception("Function pcntl_signal() failed, aborting pool start!");
                }

                $this->log->info("Starting daemon process: {$childPid}");

                $this->pool = $pool;
                $this->pool->run();

                $this->log->info("Daemon process {$childPid} complete.");

                exit(0);
                break;

            case -1: // Error
                $msg = pcntl_strerror(pcntl_errno());
                throw new Exception("Function pcntl_fork() failed: {$msg}");

            default: // Parent
                $this->log->debug("Forked worker process: {$pid}");
        }

        return $pid;
    }

    /**
     * @param int $pid
     *
     * @return int Returns WIN32_NO_ERROR on success, FALSE if there is a problem with the parameters or a Win32 Error Code on failure.
     */
    public function stop($pid)
    {
        $this->log->debug("Sending shutdown signal to daemon: {$pid}\n");
        posix_kill($pid, SIGTERM);
        $status = posix_get_last_error();

        return $status;
    }

    /**
     * Signal handler for child process signals.
     *
     * @param int $number
     */
    public function signal($number)
    {
        $this->log->info("Daemon received signal: {$number}");

        switch ($number) {
            case SIGTERM:
                $this->pool->resize(0);

            case SIGINT:
                $this->pool->shutdown();
                break;

            case SIGHUP:


            default:
                // handle all other signals
        }
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
