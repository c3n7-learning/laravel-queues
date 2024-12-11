<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class Deploy implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $uuid = (string) random_int(1_000, 9_999);
        info("Started Deploying {$uuid}");

        sleep(5);

        info("Finished Deploying! {$uuid}");
    }


    public function middleware()
    {
        // Acts a circuit breaker that delays the job if it fails due to exceptions
        // If the job fails, this middleware releases this job back to the queue up to X times, with a delay of Y seconds
        return [
            new \Illuminate\Queue\Middleware\ThrottlesExceptions(10, 100),
        ];
    }
}
