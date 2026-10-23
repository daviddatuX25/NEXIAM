<?php

use App\Http\Controllers\Walkthrough\WalkthroughProgressController;
use Illuminate\Support\Facades\Route;

Route::post('/walkthrough/progress', [WalkthroughProgressController::class, 'store'])
    ->middleware('auth')
    ->name('walkthrough.progress.store');
