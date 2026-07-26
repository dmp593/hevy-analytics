<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\BodyCompositionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\MuscleController;
use App\Http\Controllers\NutritionController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressPhotoController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\StrengthLevelController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\WriteBackController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::view('/guide', 'guide.index')->name('guide');

    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance');
    Route::get('/performance/data', [PerformanceController::class, 'data'])->name('performance.data');

    Route::get('/strength-levels', [StrengthLevelController::class, 'index'])->name('strength-levels');

    Route::get('/muscle', [MuscleController::class, 'index'])->name('muscle');
    Route::get('/muscle/data', [MuscleController::class, 'data'])->name('muscle.data');

    Route::get('/body', [BodyCompositionController::class, 'index'])->name('body');

    Route::get('/photos', [ProgressPhotoController::class, 'index'])->name('photos');
    Route::post('/photos', [ProgressPhotoController::class, 'store'])->middleware('throttle:30,1')->name('photos.store');
    Route::get('/photos/{photo}/file', [ProgressPhotoController::class, 'show'])->name('photos.file');
    Route::delete('/photos/{photo}', [ProgressPhotoController::class, 'destroy'])->name('photos.destroy');

    Route::get('/nutrition', [NutritionController::class, 'index'])->name('nutrition');
    Route::post('/nutrition/recompute', [NutritionController::class, 'recompute'])->name('nutrition.recompute');
    Route::post('/nutrition/intake', [NutritionController::class, 'storeIntake'])->name('nutrition.intake');

    Route::get('/projections', [ProjectionController::class, 'index'])->name('projections');

    Route::get('/routines', [RoutineController::class, 'index'])->name('routines');
    Route::get('/routines/{routine}', [RoutineController::class, 'show'])->name('routines.show');
    Route::get('/routines/{routine}/edit', [RoutineController::class, 'edit'])->name('routines.edit');

    Route::get('/goals', [GoalController::class, 'index'])->name('goals');
    Route::post('/goals', [GoalController::class, 'store'])->name('goals.store');

    Route::get('/ai', [AiController::class, 'index'])->name('ai');
    Route::post('/ai/generate', [AiController::class, 'generate'])->middleware('throttle:10,1')->name('ai.generate');

    Route::post('/sync', [SyncController::class, 'store'])->middleware('throttle:6,1')->name('sync');

    // Write-back (external Hevy mutations — throttled to prevent abuse/duplication)
    Route::get('/write-operations', [WriteBackController::class, 'index'])->name('write.index');
    Route::post('/write-operations/{operation}/confirm', [WriteBackController::class, 'confirm'])->middleware('throttle:20,1')->name('write.confirm');
    Route::post('/routines/{routine}/stage-progression', [WriteBackController::class, 'stageProgression'])->middleware('throttle:20,1')->name('write.progression');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
