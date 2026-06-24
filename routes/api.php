<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



// Routes publiques
Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
    Route::post('/reset-password', [NewPasswordController::class, 'store']);
});

// Routes protégées
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->append('profile'); 
        return $user;
    });
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

});

Route::middleware(['auth:sanctum', 'isAdmin'])->prefix('admin')->group(function () {
    Route::post('/students', [UserController::class, 'storeStudent']);
    Route::post('/teachers', [UserController::class, 'storeTeacher']);
});