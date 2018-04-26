# Legume: A multi-thread job manager for beanstalkd

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

### Creating Jobs
To create a Legume job, simply implement the [Legume\Job\HandlerInterface](src/Job/HandlerInterface.php) in your
class and add the full namespace to the "jobs" array in the configuration file.  When a job is picked up by the worker,
the `__invoke($jobId, $workload)` method will be called with the current job id and the workload used when queueing
the job.  No processing such as `unserialze()` or `json_decode()` will be applied to the workload.

### Queueing Job Workloads
Legume does not provide any direct job queueing functionality.  Instead, the library used to communicate with the 
intended job queue should be used.  The job name provided to the client must be a `callable` accessible to both the 
client and the Legume job manager with a function signature matching `function (int $jobId, string $workload): int`.

```
$client = new Pheanstalk("127.0.0.1");

$seconds = rand(15, 60 * 1);

$client->useTube(str_replace("\\", "/", Legume\Job\Handler\Example::class))
    ->put($seconds, Pheanstalk::DEFAULT_PRIORITY, Pheanstalk::DEFAULT_DELAY, Pheanstalk::DEFAULT_TTR);
```

## Configuration
Daemon configuration is all handled though a PHP file that returns an array with 4 top level elements: daemon, options,
servers and jobs.  Example configuration can be found in [conf/legumed.conf.php](conf/legumed.conf.php).


### Jobs Section
The jobs section is a numbered array of classes implementing the [JobHandlerInterface](src/JobHandlerInterface.php).
All valid classes will be registered with all workers in the pool, any invalid class will be ignored.


### Daemon Section
The daemon section contains configuration keys and values for the daemon process.

| Field          | Type     | Default             | Description
|----------------|----------|---------------------|---------------------------------------------------------------------
| user           | string   | starting user       | The username to run the pool as
| group          | string   | starting group      | The group to run the pool as
| priority       | integer  | 0                   | The priority of the pool process (niceness)
| pidFile        | string   | False               | Default pid file path if -P is not specified

### Options Section
These are the options for the pool and workers.

| Field          | Type     | Default             | Description
|----------------|----------|---------------------|---------------------------------------------------------------------
| size           | int      | 1                   | The username to run the pool as
| timeout        | int      | 300                 | The number of seconds to keep idle workers alive, -1 unlimited
| maxJobs        | integer  | -1                  | The maximum job count per worker before forced restart, -1 unlimited
| maxRuntime     | string   | -1                  | The maximum uptime per worker before forced restart, -1 unlimited

## Additional Information
Up to date source code and documentation available at:
[https://github.com/kwhat/legumed/](https://github.com/kwhat/legumed/)
