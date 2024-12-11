<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    dispatch(new \App\Jobs\Deploy());

    return view('welcome');
});
