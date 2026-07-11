<?php

use App\Http\Controllers\AnalyzeController;
use App\Http\Controllers\ButlerController;
use App\Http\Controllers\CareerWorkspaceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecommendedResumeController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('home');

Route::post('/api/analyze', [AnalyzeController::class, 'store'])
    ->middleware('throttle:analyze')
    ->name('analyze');
Route::post('/api/butler', [ButlerController::class, 'store'])
    ->middleware('throttle:butler')
    ->name('butler');

Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
Route::get('/reports/{report}/recommended-resume', [RecommendedResumeController::class, 'show'])
    ->name('reports.recommended');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/workspace', [CareerWorkspaceController::class, 'index'])->name('workspace.index');
    Route::post('/workspace', [CareerWorkspaceController::class, 'update'])->name('workspace.update');
    Route::get('/workspace/export', [CareerWorkspaceController::class, 'export'])->name('workspace.export');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
