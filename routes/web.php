<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
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

    \Illuminate\Support\Facades\Bus::chain([
        new \App\Jobs\PullRepo('laracasts/project1'),
        function () {
            \Illuminate\Support\Facades\Bus::batch([/* ... */])->dispatch();
        }
    ])->dispatch();

    return view('welcome');
});
