<?php

use App\Http\Controllers\LabController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LabController::class, 'index'])->name('lab');
Route::post('/run', [LabController::class, 'run'])->name('lab.run');
