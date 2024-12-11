# Laravel Queues in Action

### 1. Dispatching and Running Jobs

-   use `::dispatch()` to dispatch a job.
-   use `::dispatch()->delay()` to delay a job.
-   use `::dispatch()->onQueue('the-queue')` to specify the queue.

### 2. Configuring Jobs

-   `php artisan queue:work` can be horizontally scaled, by running multiple `queue:work`.
-   To prioritize queues, specify as follows: The `payments` queue will be given higher priority

```shell
php artisan queue:work --queue=payments,default
```

### 3. Handling Attempts & Failures

To specify the number fo retries in the job class

```php
public ?int $tries = 3;
```

To specify the backoff in seconds:

```php
public int $backoff = 2;
```

To specify an exponential backoff, use an array. If the number of array elements is less than the number of retries, the last value in the array will be used for the remaining retries.

```php
public array $backoff = [2, 10, 20];
```

To retry a failed job:

```shell
php artisan queue:retry the-failed_job-uuid
```

We might also want to release a job back to the queue for future retries. It accepts a delay param that will override the `$backoff` integer value. If `$tries = 3`, the number of times the job will be run is `n+1=4`.

```php
return $this->release(2);
```

Both `release()` and `Exceptions` count towards the number of `$tries`. To only count the number of exceptions alone:

```php
public int $maxExceptions = 2;
```

If we want to run some logic if a job failes, in the job class, define a `failed` method:

```php
public function failed($e)
{
    // Do some stuff
}
```

### 4. Dispatching workflows

#### 4.1 Chains

-   Chains are run one after the next, and if one failes, the subsequent jobs aren't run.
-   These are dispatched with:

```php
\Illuminate\Support\Facades\Bus::chain($chain)->dispatch();
```

#### 4.2 Batches

-   Batch jobs are run in parallel, and DO NOT depend on each other.
-   These are dispatched with:

```php
\Illuminate\Support\Facades\Bus::batch($batches)->dispatch();
```

-   If one job fails, the remaining batch is cancelled. One can override this behaviour with:
-   Adding the logic below to the handle method of the job:

```php
public function handle()
{
    if($this->batch()->cancelled()) {
    return;
    }
}
```

-   Or `allowFailures` before dispatching:

```php
\Illuminate\Support\Facades\Bus::batch($batches)->allowFailures()->dispatch();
```

### 5. More Complex Workflows

-   We have more flags

```php
\Illuminate\Support\Facades\Bus::batch($batches)
    ->catch(function ($batch, $e) {
        // If any of these jobs fail, this logic runs
    })
    ->then(function ($batch) {
        // Will run after all jobs in the batch run successfully
    })
    ->finally(function ($batch) {
        // Will run once the batch finishes, even if some of the jobs fail
    })
    ->onQueue('deployments') // Specify which queue to run this batch on
    ->onConnection('database') // Which connection to use
    ->dispatch();
```

-   To dispatch chains within a batch, use arrays in arrays. An array in a batch array is treated as a chain.

```php
$batch = [
    [
        new \App\Jobs\PullRepo('laracasts/project1'),
        new \App\Jobs\RunTests('laracasts/project1'),
        new \App\Jobs\Deploy('laracasts/project1'),
    ],
    [
        new \App\Jobs\PullRepo('laracasts/project2'),
        new \App\Jobs\RunTests('laracasts/project2'),
        new \App\Jobs\Deploy('laracasts/project2'),
    ],
];

\Illuminate\Support\Facades\Bus::batch($batch)
    ->allowFailures()
    ->dispatch();
```

-   To dispatch a batch within a chain, use a closure or dispatch the batch from within one of the jobs in the chain:

```php
\Illuminate\Support\Facades\Bus::chain([
    new \App\Jobs\PullRepo('laracasts/project1'),
    function () {
        \Illuminate\Support\Facades\Bus::batch([/* ... */])->dispatch();
    }
])->dispatch();
```

### 6. Controlling and Limiting Jobs

-   To use atomic locks so as to not have similar jobs running concurrently, in the `handle` method of your job:

```php
// First param is how long to wait before throwing an exception (could not acquire lock)
// Second param is for when we finally acquire the lock
Cache::lock('deployments')->block(10, function () {
    $uuid = (string) random_int(1_000, 9_999);
    info("Started Deploying {$uuid}");

    sleep(5);

    info("Finished Deploying! {$uuid}");
});
```

-   We can also the `Redis::funnel` method:

```php
// Let's use the redis rate limiter
Redis::funnel('deployments')
    ->allow(10) // Allow 10 instances of this job
    ->every(60) // For every 60 Seconds
    ->block(10) // Block execution for 10 sec to wait for a lock to be acquired
    ->then(function () {
        $uuid = (string) random_int(1_000, 9_999);
        info("Started Deploying {$uuid}");

        sleep(5);

        info("Finished Deploying! {$uuid}");
    });
```

-   For rate limiting using `Redis`:

```php
// Let's use the redis concurrency limiter
Redis::throttle('deployments')
    ->limit(5) // Only 5 instances of the job can run at any given time
    ->block(10) // Block execution for 10 sec to wait for a lock to be acquired
    ->then(function () {
        $uuid = (string) random_int(1_000, 9_999);
        info("Started Deploying {$uuid}");

        sleep(5);

        info("Finished Deploying! {$uuid}");
    });
```

-   To prevent overlapping jobs from being run at the same time:

```php
public function middleware()
{
    // Will prevent running this job while another instance is in progress
    // However, this won't block but rather release the job back to the queue
    // if another job is in progress
    return [
        new \Illuminate\Queue\Middleware\WithoutOverlapping('deployments', 10),
    ];
}
```

### 7. More Job Configurations

-   If we want to have only one instance in the queue, make the job implement the `ShouldUnique` interface.

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class YourJob implements ShouldQueue, ShouldBeUnique
{
    // ...
}
```

-   By default the, the class name is used as the unique identifier. If we want to override this, override the `uniqueId` function

```php
public function uniqueId()
{
    return 'deployments';
}
```

-   We can specify how long this unique lock will last. By default a job releases it's lock once it is done processing, but if something happens like an exception, the lock won't be released. We can override this by:

```php
public function uniqueFor()
{
    return 60; // 60 Seconds
}
```

-   If we want to prevent dispatching until the current job starts processing, we can use the `ShouldBeUniqueUntilProcessing` interface.

```php
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

class YourJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    // ...
}
```

-   If we want to throttle based on exceptions:

```php
public function middleware()
{
    // Acts a circuit breaker that delays the job if it fails due to exceptions
    // If the job fails, this middleware releases this job back to the queue up to X times, with a delay of Y seconds
    return [
        new \Illuminate\Queue\Middleware\ThrottlesExceptions(10, 100),
    ];
}
```

### 8. Designing Reliable Jobs

-   If we want to dispatch a job after a commit (when using database transaction locks), use the `afterCommit` switch;

```php
YourJob::dispatch()->afterCommit();
```

-   We can make this the default behaviour by changing the configs in `queue.php` e.g.:

```php
'database' => [
    // ...
    'after_commit' => true, // set this to true
],
```

-   `SerializesModels` traits means the models passed as part of the job's constructor will be converted to simple php objects, with the model type and key. The other data will be loaded when the job runs.
-   Storing sensitive data in a job exposes it to anyone with access to the queue store. Beware of this. To encrypt the job, we can implement the `ShouldBeEncrypted` interface.

```php
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

class YourJob implements ShouldQueue, ShouldBeEncrypted
{
    // ...
}
```

### 9. Deployments

-   We can use a tool like `supervisord` to manage the queue workers, and restart them if they fail.
-   [Refer here for supervisor docs](https://laravel.com/docs/11.x/queues#supervisor-configuration)
-   We can send a signal for queue workers to restart using `php artisan queue:restart`.
-   If we intend to stop all queue workers when doing heavy updates like database migrations that might affect the queue, stop the supervisor

```shell
sudo supervisorctl stop "laravel-worker:*"
php artisan migrate --force
sudo supervisorctl start "laravel-worker:*"
```

### 10. Scaling Workers

-   One way is to add more servers to your cluster.
-   When you want to scale workers on the same maching, we can use `supervisord` and `cron`

```shell
[program:extra-workers]
process_name=%(program_name)s_%(process_num)02d
command=php /home/forge/app.com/artisan queue:work
autostart=false # We don't want the job starting automatically
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=3
redirect_stderr=true
stdout_logfile=/home/forge/app.com/extra-workers.log
stopwaitsecs=3600
```

-   If we know our job receives a lot of lot at a specific time of the day, we can use cron. In `/etc/crontab`

```shell
# At 7am every day
0 7 * * * root supervisorctl start extra-workers:*

# At 11am every day
0 11 * * * root supervisorctl start extra-workers:*
```

-   We can also periodically start a bunch of queue workers, and they can exit if there aren't jobs in the queue using the `--stop-when-empty` option.
-   We can also stop a worker after X seconds even when the queue is still busy.

```shell
# Every 10 minutes
*/10 * * * * forge php /home/forge/app.com/artisan queue:work --stop-when-empty --max-time=540 # 10 seconds
```

-   You can also explore horizon if using the `redis` queue:

```php
'supervisor1' => [
    'queue' => ['deployments', 'notifications'],
    'balance' => 'auto', // Ensure it is auto. "Time to clear" algorithm is preferred where the queue with jobs taking more time will be assigned more workers
    'minProceses' => 1,
    'maxProceses' => 10,
    'balanceMaxShift' => 3,
    'balanceCooldown' => 1,
]
```

-   To control the rate of scaling, configure `balanceMaxShift` and `balanceCooldown`:

```php
'supervisor1' => [
    // Add or remove 3 workers every second
    'balanceMaxShift' => 3,
    'balanceCooldown' => 1,
]
```

### 11. Configurations Reference

Below are all is the reference for the `queue:work` command:

```ascii
$ php artisan queue:work --help
Description:
  Start processing jobs on the queue as a daemon

Usage:
  queue:work [options] [--] [<connection>]

Arguments:
  connection                 The name of the queue connection to work

Options:
      --name[=NAME]          The name of the worker [default: "default"] (Useful when debugging to see which worker has issues)
      --queue[=QUEUE]        The names of the queues to work (Comma separated if more than one, the priority is configured by ordering them from left to right)
      --once                 Only process the next job on the queue (Useful if you want to restart the worker after each job is run. Very expensive though performance-wise)
      --stop-when-empty      Stop when the queue is empty
      --backoff[=BACKOFF]    The number of seconds to wait before retrying a job that encountered an uncaught exception [default: "0"] (Also supports an array/comma separated values)
      --max-jobs[=MAX-JOBS]  The number of jobs to process before stopping [default: "0"]
      --max-time[=MAX-TIME]  The maximum number of seconds the worker should run [default: "0"]
      --force                Force the worker to run even in maintenance mode
      --memory[=MEMORY]      The memory limit in megabytes [default: "128"] (Instruct the worker to exit once the php worker detects X memory has been allocated)
      --sleep[=SLEEP]        Number of seconds to sleep when no job is available [default: "3"]
      --rest[=REST]          Number of seconds to rest between jobs [default: "0"] (Useful e.g when communicating with thirdparties)
      --timeout[=TIMEOUT]    The number of seconds a child process can run [default: "60"] (Maximum number of seconds a job can run before being terminated)
      --tries[=TRIES]        Number of times to attempt a job before logging it failed [default: "1"]
      --json                 Output the queue worker information as JSON
  -h, --help                 Display help for the given command. When no command is given display help for the list command
      --silent               Do not output any message
  -q, --quiet                Only errors are displayed. All other output is suppressed
  -V, --version              Display this application version
      --ansi|--no-ansi       Force (or disable --no-ansi) ANSI output
  -n, --no-interaction       Do not ask any interactive question
      --env[=ENV]            The environment the command should run under
  -v|vv|vvv, --verbose       Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

-   Let's go over the switches in our Job Class

```php
class YourJob implements ShouldQueue
{
    use Queueable;

    public $connection = 'redis';
    public $queue = 'notifications';
    public $backoff = 30; // In seconds, array or method
    public $timeout = 60; // Run this job for X seconds before calling it failed
    public $tries = 3; // Number of tries
    public $delay = 300; // Delay in Sec that will be used before dispatching this job to the queue.
    public $afterCommit = true; // When doing DB Transactions
    public $shouldBeEncrypted = true; // Encrypt the job before storing it in the queue store.

    // When implementing the ShouldBeUnique or ShouldBeUniqueUntilProcessing intefaces
    // Override the unique id
    public $uniqueId = 'products';
    public function uniqueId() {
        return 'some-unique-id';
    }
    // We can also define the no. of seconds the unique lock should be in place before auto-releasing
    public $uniqeFor = 10; // Seconds


    // When SerializesModels is used, when unserializing a job and we do not find the record, the default behaviour is to throw a ModelNotFoundException
    // This config below tells laravel to just delete the job from the queue when this happens, and not throw an exception.
    public $deleteWhenMissingModels = true;

    // Retry until date time
    public $tries = 0; // This means retry indefinitely so we can rely on retryUntil.

    public function retryUntil() {
        return now()->addDay();
    }
}
```

-   As for the configurations in `queue.php`

```php
'database' => [
    'driver' => 'database',
    'connection' => env('DB_QUEUE_CONNECTION'),
    'table' => env('DB_QUEUE_TABLE', 'jobs'),
    'queue' => env('DB_QUEUE', 'default'),

    'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90), // Number of seconds a job remains reserved before being released back to the queue if a job hangs. Always ensure this greater than the $timeOut in any job.
    // Otherwise the job will be released back to the queue while another instance of it is still being processed by a worker.

    'after_commit' => false,
],

'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
    'block_for' => null, // If configured, it will keep the redis/beanstalk connection open and wait for jobs to be available for X seconds, if we want to save on resources/networking resources.
    'after_commit' => false,
],
```
