<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $chain = [
        new \App\Jobs\PullRepo('laracasts/project1'),
        new \App\Jobs\PullRepo('laracasts/project2'),
        new \App\Jobs\PullRepo('laracasts/project3'),
    ];

    // \Illuminate\Support\Facades\Bus::chain($chain)->dispatch();
    \Illuminate\Support\Facades\Bus::batch($chain)
        ->allowFailures()
        ->dispatch();

    return view('welcome');
});
