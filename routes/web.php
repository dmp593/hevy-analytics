<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\BodyCompositionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MuscleController;
use App\Http\Controllers\NutritionController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProgressPhotoController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\StrengthLevelController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\UnitSystemController;
use App\Http\Controllers\WriteBackController;
use Illuminate\Support\Facades\Route;

// Available to guests: the login page is the first thing anyone sees.
Route::post('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

// A signed-in athlete wants their dashboard, not the pitch. A signed-out one
// gets the page that explains what this is before being asked to register —
// which is also the only page that states the Hevy API key requirement early
// enough to be useful.
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('landing');
})->name('home');

// The one-click demo. Guest-only and rate limited: it performs a login, and a
// login endpoint with no throttle is a free session-fixation playground.
Route::post('/demo', [DemoController::class, 'enter'])
    ->middleware(['guest', 'throttle:10,1'])->name('demo.enter');
Route::post('/demo/leave', [DemoController::class, 'leave'])
    ->middleware('auth')->name('demo.leave');

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

    // CSV import: the data door for accounts the API cannot serve — Hevy only
    // issues API keys to Pro subscribers, the CSV export is available to all.
    Route::get('/import', [ImportController::class, 'index'])->name('import');
    Route::post('/import', [ImportController::class, 'store'])
        ->middleware('throttle:10,10')->name('import.store');

    // Write-back (external Hevy mutations — throttled to prevent abuse/duplication)
    Route::get('/write-operations', [WriteBackController::class, 'index'])->name('write.index');
    Route::post('/write-operations/{operation}/confirm', [WriteBackController::class, 'confirm'])->middleware('throttle:20,1')->name('write.confirm');
    Route::post('/routines/{routine}/stage-progression', [WriteBackController::class, 'stageProgression'])->middleware('throttle:20,1')->name('write.progression');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // One-tap unit switching for the dashboard welcome card; the profile form
    // sets the same column at full length.
    Route::post('/settings/units/{system}', UnitSystemController::class)->name('settings.units');

    // AI provider settings. Separate from the profile form because the two are
    // saved independently: changing a training preference should not require
    // re-submitting an API key, and vice versa.
    Route::put('/settings/ai', [AiSettingsController::class, 'update'])->name('settings.ai.update');
    Route::delete('/settings/ai/{provider}', [AiSettingsController::class, 'destroy'])->name('settings.ai.destroy');

    // GDPR: everything the app holds about you, and the ability to take it away.
    Route::get('/settings/export', [DataExportController::class, 'download'])->name('settings.export');

    // Billing. Deliberately reachable on every tier: someone on the free tier
    // needs to be able to subscribe, and someone whose card is failing needs to
    // be able to fix it.
    Route::get('/billing', [SubscriptionController::class, 'show'])->name('billing');
    Route::post('/billing/cancel', [SubscriptionController::class, 'cancel'])->name('billing.cancel');
    Route::post('/billing/resume', [SubscriptionController::class, 'resume'])->name('billing.resume');

    /*
     * Admin. Behind the `admin` middleware, which 404s rather than 403s so a
     * non-admin does not learn the area exists.
     */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::post('/users/{user}/grant', [AdminUserController::class, 'grant'])->name('users.grant');
        Route::post('/users/{user}/revoke', [AdminUserController::class, 'revoke'])->name('users.revoke');
        Route::post('/users/{user}/cancel-subscription', [AdminUserController::class, 'cancelSubscription'])->name('users.cancel');
    });
});

require __DIR__.'/auth.php';
