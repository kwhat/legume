<?php return array(
    "daemon" => [
        "user" => "nobody", // Process user for pool and workers.
        "group" => "nobody", // Process group for pool and workers.
        "priority" => 10, // Process niceness for this pool. (-20 to 19)
    ],
    "options" => [
        "size" => 32, // Maximum number of workers to start.
        "timeout" => 5,//5 * 60, // Number of seconds to wait for a gearman job to arrive at the worker, -1 for infinite.
        "maxJobs" => -1, // Max job count per worker before restart, -1 for infinite.
        "maxRuntime" => -1 // Max lifetime in seconds to keep a worker alive before force restart.
    ],
    "servers" => [
        "127.0.0.1:4730"
    ],
    "jobs" => [
        Legume\Job\Handler\Example::class
    ]
);
