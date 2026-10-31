<?php

use App\Http\Controllers\LabController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LabController::class, 'index'])->name('lab');
Route::get('/runtime-status', [LabController::class, 'runtimeStatus'])->name('lab.runtime-status');
Route::post('/ctf/start', [LabController::class, 'startCtfRound'])->name('ctf.start');
Route::post('/ctf/submit', [LabController::class, 'submitCtfFlag'])->name('ctf.submit');
Route::post('/run', [LabController::class, 'run'])->name('lab.run');
Route::post('/execute', [LabController::class, 'execute'])->name('lab.execute');
Route::post('/session-experiment', [LabController::class, 'sessionExperiment'])->name('lab.session');
Route::post('/serialize-sample', [LabController::class, 'serializeSample'])->name('lab.serialize');
Route::post('/object-lifecycle', [LabController::class, 'objectLifecycle'])->name('lab.object-lifecycle');
