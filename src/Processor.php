<?php

namespace Legume;

use Exception;
use GetOpt\ArgumentException;
use GetOpt\ArgumentException\Missing;
use GetOpt\Command;
use GetOpt\GetOpt;
use GetOpt\Option;
use Legume\Job\Manager\ThreadPool;
use Legume\Job\QueueAdapter\PheanstalkAdapter;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use Psr\Container\ContainerInterface as DI;

class Processor
{
    const AUTOLOAD = "autoload";
    const LOGGER = "logger";

    /**
     * @var DI $container
     */
    private $container;

    /**
     * Service constructor.
     *
     * @param DI|array $container Either a ContainerInterface or an associative array of app settings
     */
    public function __construct($container = [])
    {
        $this->container = new Pimple($container);

        $this->loadDependencies($this->container);
    }

    /**
     * @param DI $container
     */
    protected function loadDependencies(DI $container)
    {
        $container[GetOpt::class] = function (DI $container) {
            $queueOpts = array(
                Option::create('h', "host", GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription("Hostname or IP address of the job queue")
                    ->setDefaultValue("localhost"),

                Option::create('p', "port", GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription("Port number of the job queue")
                    ->setDefaultValue(PheanstalkInterface::DEFAULT_PORT),

                Option::create('t', "timeout", GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription("The socket timeout for connecting to the job queue")
                    ->setValidation('is_numeric'),
            );

            $managerOpts = array(
                Option::create('j', "jobs", GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription("")
                    ->setValidation('file_exists'),

                Option::create('s', "size", GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription("The maximum parallel jobs to managed by the pool")
                    ->setValidation('is_numeric')
                    ->setDefaultValue(1),
            );

            $suExecOpts = array(
                Option::create('u', "user", GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription("Username to suExec the job manager process"),
                Option::create('g', "group", GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription("Groupname to suExec the job manager process"),
                Option::create('n', "nice", GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription("The system priority for the job manager process process")
            );


            $cli = new GetOpt();
            $cli->addCommands(array(
                Command::create("start", [$this, "start"])
                    ->setDescription("Start the Legume job manager")
                    ->addOptions(array_merge($queueOpts, $managerOpts, $suExecOpts, array(
                        Option::create('D', "daemon")
                            ->setDescription("Run the job manager as a background process")
                            ->setDefaultValue(false)
                    ))),

                Command::create("stop", "Processor::stop")
                    ->setDescription('Stop a job manager background process'),

                Command::create("restart", "Processor::restart")
                    ->setDescription("Restart a job manager background process")
                    ->addOptions(array_merge($queueOpts, $managerOpts, $suExecOpts)),

                Command::create("reload", "Processor::reload")
                    ->setDescription("Reload a job manager background process configuration")
                    ->addOptions(array_merge($queueOpts, $managerOpts, $suExecOpts))
            ))
            ->addOptions(array(
                Option::create('H', "help")
                    ->setDescription("Display a command contextual help"),

                Option::create('P', "pid", GetOpt::REQUIRED_ARGUMENT)
                    ->setDescription("A file to store the pid of the job manager process"),

                Option::create('v', "verbose", GetOpt::OPTIONAL_ARGUMENT)
                    ->setDescription("Set the verbosity of the job manager")
                    ->setValidation('is_numeric')
                    ->setValue(6)
            ));

            return $cli;
        };

        $this->container[static::LOGGER] = function (DI $container) {
            /** @var GetOpt $opts */
            $opts = $container->get(GetOpt::class);

            $log = new Logger(basename($_SERVER["SCRIPT_FILENAME"], ".php"));
            $log->pushHandler(new SyslogHandler($log->getName(), LOG_SYSLOG, Logger::WARNING));

            $levelMap = array(
                Logger::EMERGENCY,
                Logger::ALERT,
                Logger::CRITICAL,
                Logger::ERROR,
                Logger::WARNING,
                Logger::NOTICE,
                Logger::INFO,
                Logger::DEBUG
            );

            $level = $levelMap[$opts->getOption("verbose")];
            $log->pushHandler(new StreamHandler("php://stdout", $level));

            return $log;
        };


        $container[Pheanstalk::class] = function (DI $container) {
            /** @var GetOpt $opts */
            $opts = $container->get(GetOpt::class);

            $host = $opts->getOption("host");
            $port = $opts->getOption("port");
            $timeout = $opts->getOption("timeout");

            $client = new Pheanstalk($host, $port, $timeout);

            return $client;
        };

        $container[PheanstalkAdapter::class] = function (DI $container) {
            $adaptor = new PheanstalkAdapter($container);
            $adaptor->setLogger($container->get(static::LOGGER));

            $opts = $container->get(GetOpt::class);
            $jobs = array(Job\Handler\Example::class);
            $jobFile = $opts->getOption("jobs");
            if ($jobFile !== null) {
                $jobs = include($jobFile);
            }

            foreach ($jobs as $job) {
                $adaptor->register($job);
            }
            $adaptor->unregister(Pheanstalk::DEFAULT_TUBE);

            return $adaptor;
        };


        $container[ThreadPool::class] = function (DI $container) {
            /** @var GetOpt $opts */
            $opts = $container->get(GetOpt::class);
            
            /** @var PheanstalkAdapter $adapter */
            $adapter = $container->get(PheanstalkAdapter::class);
            /** @var string $autoloader */
            $autoloader = $container->get(static::AUTOLOAD);

            /** @var int $size */
            $size = $opts->getOption("size");

            $pool = new ThreadPool($adapter, $autoloader);
            $pool->setLogger($container->get(static::LOGGER));
            $pool->resize($size);

            return $pool;
        };


        $container[Job\Handler\Example::class] = function (DI $container) {
            $job = new Job\Handler\Example($container);
            $job->setLogger($container->get(static::LOGGER));

            return $job;
        };
    }

    /**
     * @return int|bool
     * @throws Exception
     */
    protected function readPid()
    {
        /** @var GetOpt $opts */
        $opts = $this->container->get(GetOpt::class);

        $pid = false;
        $pidFile = $opts->getOption("pid");
        if ($pidFile !== null) {
            if (! file_exists($pidFile)) {
                throw new Exception("The pid file does not exists!");
            }

            $pid = file_get_contents($pidFile);
        }

        return $pid;
    }

    /**
     * @param int $pid
     *
     * @return bool
     * @throws Exception
     */
    protected function writePid($pid)
    {
        /** @var GetOpt $opts */
        $opts = $this->container->get(GetOpt::class);

        $success = false;
        $pidFile = $opts->getOption("pid");
        if ($pidFile !== null) {
            if (file_exists($pidFile)) {
                throw new Exception("The pid file already exists!");
            }

            $success = (file_put_contents($pidFile, $pid) > 0);
        }

        return $success;
    }

    protected function suExec()
    {
        /** @var GetOpt $opts */
        $opts = $this->container->get(GetOpt::class);

        $niceness = $opts->getOption("nice");
        if ($niceness !== null) {
            if (! pcntl_setpriority($niceness, getmypid())) {
                throw new Exception("Failed to set processor priority.");
            }
        }

        $group = $opts->getOption("group");
        if ($group !== null) {
            /** @var array $groupInfo */
            $groupInfo = posix_getgrnam($group);
            if (! isset($groupInfo["gid"])) {
                throw new Exception("Invalid processor group.");
            } else if (! posix_setgid($groupInfo["gid"])) {
                throw new Exception("Failed to change processor group.");
            }
        }

        $user = $opts->getOption("user");
        if ($user !== null) {
            /** @var array $userInfo */
            $userInfo = posix_getpwnam($user);
            if (! isset($userInfo["uid"])) {
                throw new Exception("Invalid pool user.");
            } else if (! posix_setuid($userInfo["uid"])) {
                throw new Exception("Failed to change pool user.");
            }
        }
    }

    protected function start()
    {
        /** @var GetOpt $opts */
        $opts = $this->container->get(GetOpt::class);

        /** @var ThreadPool $workerPool */
        $workerPool = $this->container->get(ThreadPool::class);

        // Set the user, group and priority for this process.
        $this->suExec();

        if ($opts->getOption("daemon")) {
            if (strcasecmp(substr(PHP_OS, 0, 3), "Win") == 0) {
                $pid = null;
            } else {
                $daemon = new Daemon();
                $daemon->setLogger($this->container->get(static::LOGGER));
                $pid = $daemon->start($workerPool);
            }
        } else {
            $pid = getmypid();
        }

        if (! $this->writePid($pid)) {
            //$this->log->alert("Failed to write pid to file!");
        }

        if (! $opts->getOption("daemon")) {
            $workerPool->run();
        }
    }

    protected function stop()
    {
        $pid = $this->readPid();
        if ($pid === false) {
            throw new Exception("Missing or invalid pid file provided");
        }

        if (strcasecmp(substr(PHP_OS, 0, 3), "Win") == 0) {
            $pid = null;
        } else {
            $daemon = new Daemon();
            $daemon->stop($pid);
        }
    }

    public function run()
    {
        /** @var GetOpt $opts */
        $opts = $this->container->get(GetOpt::class);

        try {
            try {
                $opts->process();
            } catch (Missing $exception) {
                // catch missing exceptions if help is requested
                if (! $opts->getOption('help')) {
                    throw $exception;
                }
            }
        } catch (ArgumentException $exception) {
            file_put_contents('php://stderr', $exception->getMessage() . PHP_EOL);
            echo PHP_EOL . $opts->getHelpText();
            exit;
        }

        // show help and quit
        $command = $opts->getCommand();
        if (! $command || $opts->getOption("help")) {
            echo $opts->getHelpText();
            exit;
        }

        // call the requested command
        $status = call_user_func($command->getHandler());

        return $status;
    }
}
