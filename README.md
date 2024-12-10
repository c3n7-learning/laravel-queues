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

#### 4.2 Chains

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
