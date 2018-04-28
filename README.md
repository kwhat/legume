# Legume: Multi-thread job manager and daemon for beanstalkd

Creating and managing a pool of workers for parallel processing can be a challenging endeavor.  Fortunately,
Legume provides a simple interface for creating a pool to track currently running workers, and adjust
the pool size based on job load.  The daemon also offers user, group and priority switching for complete control
of the forked processes.

## Usage
This project has two usage paths, one for running the daemon, and one for queueing worker jobs.

### Daemon Control
The daemon manager can be accessed via `./bin/legumed --help`.

```
 # bin/legumed -H
   Usage: bin/legumed <command> [options] [operands]

   Options:
     -H, --help
     -P, --pid <arg>
     -v, --verbose <arg>

   Commands:
     start    Start the worker
     stop     Stop the worker
```

To start the daemon, simply call `./bin/legumed start`.  If you would like to start a background process,
add the `-D, --daemon` flag.  To stop the daemon, run `./bin/legumed stop`.  If you started the daemon
without `-D, --daemon`, SIGTERM or SIGINT will halt the foreground process.  Please note, if you are trying
to start the daemon and receive an exception regarding changing the pool's user, group or priority, you
need to start `./bin/legumed` with privileges using the root user or sudo command.

## Job Configuration
Available jobs are configured via [conf/legumed.conf.php](conf/legumed.conf.php).  This file contains an associative 
array of job names mapped to a [callable](http://php.net/manual/en/language.types.callable.php) or classname string 
implementing the [JobHandlerInterface](src/JobHandlerInterface.php).  All valid classes will be registered with all 
workers in the pool, any invalid class will be ignored.

### Creating Jobs
To create a Legume job, simply implement the [Legume\Job\HandlerInterface](src/Job/HandlerInterface.php) in your
class and add the full namespace to the job configuration file.  When a job is picked up by the worker, the 
`__invoke($jobId, $workload)` method will be called with the current job id and the workload used when queueing
the job.  No processing such as `unserialze()` or `json_decode()` will be applied to the workload.

### Queueing Job Workloads
Legume does not provide any direct job queueing functionality.  Instead, the library used to communicate with the 
intended job queue should be used.  The job name provided to the client must match a configured with one or more legume 
instances using the `start` command with the `--jobs` flag.

```
$client = new Pheanstalk("127.0.0.1");

$seconds = rand(15, 60 * 1);

$client->useTube("ExampleJob")
    ->put($seconds, Pheanstalk::DEFAULT_PRIORITY, Pheanstalk::DEFAULT_DELAY, Pheanstalk::DEFAULT_TTR);
```

## Additional Information
Up to date source code and documentation available at:
[https://github.com/kwhat/legumed/](https://github.com/kwhat/legume/)
