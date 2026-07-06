<?php

use Illuminate\Support\Facades\Route;
use SparrowhawkLabs\Nawate\Http\Controllers\NawateStateController;

Route::middleware(['web', 'signed'])
    ->get('/nawate/state/{token}', NawateStateController::class)
    ->name('nawate.state');
