# Legume: A multi-thread job manager and daemon
A robust light weight thread based job manager with Windows service and Linux OS X daemon support.

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
     restart  Restart the worker
     reload   Reload the worker configuration
```

To start the daemon, simply call `./bin/legumed start`.  If you would like to start a background process,
add the `-D, --daemon` flag.  To stop the daemon, run `./bin/legumed stop`.  If you started the daemon
without `-D, --daemon`, SIGTERM or SIGINT will halt the foreground process.  Please note, if you are trying
to start the daemon and receive an exception regarding changing the pool's user, group or priority, you
need to start `./bin/legumed` with privileges using the root user or sudo command.

### Creating Jobs
To create a Legume job, simply implement the [Legume\Job\HandlerInterface](src/Job/HandlerInterface.php) in your
class and add the full namespace to the "jobs" array in the configuration file.  When a job is picked up by the worker,
the `run($jobId, $workload)` method will be called with the current Gearman job id and the workload used when queueing
the job.  No processing such as unserialze or json_decode will be applied to the workload.

### Queueing Job Workloads
Add jobs to the Legume queue is pretty straight forward.  Please note, the job handle should be the fully namespaced
class that implements [Legume\Job\HandlerInterface](src/Job/HandlerInterface.php).  For a complete exmaple, see
[bin/example](bin/example) for queueing jobs and [src/JobHandler/Example.php](src/JobHandler/Example.php) for the job
handler.

```
$client = new GearmanClient();
$client->addServer("127.0.0.1", 4730);

...
$workload = "Hello World!";
$result = $client->doBackground(MyProject\Jobs\Job::class, $workload, uniqid());

```

## Configuration
Daemon configuration is all handled though a PHP file that returns an array with 4 top level elements: daemon, options,
servers and jobs.  Example configuration can be found in [conf/gearmandwd.php](conf/gearmandwd.php).

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

### Servers Section
This filed should contain a numbered array of gearmand server addresses.  The addresses should be an IP address,
valid hostname, or FQDN.  An optional port maybe appended to the address of the server, if not specified, the **default
port 4730** will be used.

```
"servers" => [
    "127.0.0.1:4730",
    "localhost",
    "my.domain.tld:4790"
],
```

### Jobs Section
The jobs section is a numbered array of classes implementing the [JobHandlerInterface](src/JobHandlerInterface.php).
All valid classes will be registered with all workers in the pool, any invalid class will be ignored.

```
"jobs" => [
    GearmanWD\JobHandler\Example::class,
    "MyProject\\Jobs\\MyGearmanWorker"
],
```

## Additional Information
Up to date source code and documentation available at:
[https://github.com/kwhat/legume/](https://github.com/kwhat/legume/)