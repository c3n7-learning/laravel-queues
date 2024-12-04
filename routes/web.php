<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    \App\Jobs\SendWelcomeEmail::dispatch();

    return view('welcome');
});
