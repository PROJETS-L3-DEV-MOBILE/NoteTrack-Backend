<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\UEController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
    Route::post('/reset-password', [NewPasswordController::class, 'store']);
});

Route::middleware('auth:sanctum')->post('/logout', [AuthenticatedSessionController::class, 'destroy']);

Route::middleware(['auth:sanctum', 'ability:issue-access-token'])->post('/refresh', [AuthenticatedSessionController::class, 'refresh']);

Route::middleware(['auth:sanctum', 'ability:access-api'])->group(function () {

    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->append('profile');
        return $user;
    });

    Route::middleware('isAdmin')->prefix('admin')->group(function () {
        Route::post('/students', [UserController::class, 'storeStudent']);
        Route::post('/teachers', [UserController::class, 'storeTeacher']);

        Route::apiResource('classes', \App\Http\Controllers\ClasseController::class);

        Route::prefix('subjects')->group(function () {
            Route::get('/', [SubjectController::class, 'index']);
        });

        Route::apiResource('ues', UEController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('ues.subjects', SubjectController::class)->scoped()->only(['store', 'update', 'destroy']);

        Route::prefix('dashboard')->group(function () {
            Route::get('/stats', [DashboardController::class, 'stats']);
            Route::get('/results', [DashboardController::class, 'results']);
            Route::get('/recent-activities', [DashboardController::class, 'recentActivities']);
            Route::get('/latest-notes', [DashboardController::class, 'latestNotes']);
            Route::get('/recent-subjects', [DashboardController::class, 'recentSubjects']);
        });
    });
});
