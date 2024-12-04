<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // To delay dispatching by 5 sec
    // \App\Jobs\SendWelcomeEmail::dispatch()->delay(5);

    foreach (range(1, 10) as $i) {
        \App\Jobs\SendWelcomeEmail::dispatch();
    }

    \App\Jobs\ProcessPayment::dispatch()->onQueue('payments');

    return view('welcome');
});
