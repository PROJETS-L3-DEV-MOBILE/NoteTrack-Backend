<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\UEController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\ClasseController;
use App\Http\Controllers\Admin\NoteController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\StudentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth (Guest)
Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
    Route::post('/reset-password', [NewPasswordController::class, 'store']);
});

Route::middleware('auth:sanctum')->post('/logout', [AuthenticatedSessionController::class, 'destroy']);
Route::middleware(['auth:sanctum', 'ability:issue-access-token'])->post('/refresh', [AuthenticatedSessionController::class, 'refresh']);

// Authenticated Routes
Route::middleware(['auth:sanctum', 'ability:access-api'])->group(function () {

    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->append('profile');
        return $user;
    });

    // Admin Group
    Route::middleware('isAdmin')->prefix('admin')->group(function () {

        // Teachers
        Route::apiResource('/teachers', TeacherController::class);

        // Students
        Route::apiResource('/students', StudentController::class);

        // Classes
        Route::apiResource('classes', ClasseController::class);

        // promotions
        Route::apiResource('/promotions', PromotionController::class);

        // Notes
        Route::prefix('subjects/{subject_id}/notes')->group(function () {
            Route::get('/', [NoteController::class, 'indexBySubject']);
            Route::post('/', [NoteController::class, 'store']);
            Route::patch('/publish', [NoteController::class, 'bulkPublish']);
            Route::patch('/lock', [NoteController::class, 'bulkLock']);
        });

        Route::prefix('notes/{note}')->group(function () {
            Route::patch('/publish', [NoteController::class, 'publish']);
            Route::patch('/lock', [NoteController::class, 'lock']);
        });

        Route::apiResource('notes', NoteController::class)->only(['show', 'update', 'destroy']);

        // Subjects
        Route::prefix('subjects')->group(function () {
            Route::get('/', [SubjectController::class, 'index']);
        });

        // UEs & Nested Subjects
        Route::apiResource('ues', UEController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('ues.subjects', SubjectController::class)->scoped()->only(['store', 'update', 'destroy']);

        // Dashboard
        Route::prefix('dashboard')->group(function () {
            Route::get('/stats', [DashboardController::class, 'stats']);
            Route::get('/results', [DashboardController::class, 'results']);
            Route::get('/recent-activities', [DashboardController::class, 'recentActivities']);
            Route::get('/latest-notes', [DashboardController::class, 'latestNotes']);
            Route::get('/recent-subjects', [DashboardController::class, 'recentSubjects']);
        });
    });
});
