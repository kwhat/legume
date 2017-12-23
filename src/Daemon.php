<?php
declare(ticks = 1);
namespace Legume;

use Exception;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class Daemon implements LoggerAwareInterface
{
    /** @var LoggerInterface $log */
    protected $log;

    /** @var Job\ManagerInterface $pool */
    protected $pool;

    public function __construct()
    {
        $this->log = new Logger(basename($_SERVER["SCRIPT_FILENAME"], ".php"));
        $this->log->pushHandler(new SyslogHandler($this->log->getName(), LOG_SYSLOG, Logger::WARNING));
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

                $this->pool =  $pool;
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
