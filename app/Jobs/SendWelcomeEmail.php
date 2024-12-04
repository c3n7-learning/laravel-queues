<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWelcomeEmail implements ShouldQueue
{
    use Queueable;

    // Number of retries (both exceptions and releases)
    public ?int $tries = 10;

    // We can have 10 retries, but only 2 can be because of exceptions.
    public int $maxExceptions = 2;

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
        throw new \Exception('Failed');

        sleep(3);

        info('Hello!');
    }

    public function failed($e)
    {
        info('Failed, nooo');
    }
}
