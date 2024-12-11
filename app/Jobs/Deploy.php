<?php

namespace App\Jobs;

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
        // Will prevent running this job while another instance is in progress
        // However, this won't block but rather release the job back to the queue
        // if another job is in progress
        return [
            new \Illuminate\Queue\Middleware\WithoutOverlapping('deployments', 10),
        ];
    }
}
