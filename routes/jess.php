<?php

use Illuminate\Support\Facades\Route;
use SparrowhawkLabs\Jess\Http\Controllers\JessStateController;

Route::middleware(['web', 'signed'])
    ->get('/jess/state/{token}', JessStateController::class)
    ->name('jess.state');
