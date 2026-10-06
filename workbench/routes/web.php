<?php

// Host-application routes — must win over Sunrice's catch-all.
use Illuminate\Support\Facades\Route;

Route::get('/host-page', fn () => 'host page')->name('host-page');
