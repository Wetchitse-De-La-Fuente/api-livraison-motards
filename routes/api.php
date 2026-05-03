<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UtilisateurController;
use App\Http\Controllers\API\CourseController;
use App\Http\Controllers\API\NotificationController;

Route::middleware('redirectifauthenticatedapi')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:api')->group(function () {

    Route::get('/motards/online', [UtilisateurController::class, 'motardsOnline']);
    Route::patch('/motards/toggle-online', [UtilisateurController::class, 'toggleOnline']);
    Route::patch('/motards/update-location', [UtilisateurController::class, 'updateLocation']);

    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{id}', [CourseController::class, 'show']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::patch('/courses/{id}/assign-motard', [CourseController::class, 'assignMotard']);
    Route::patch('/courses/{id}/status', [CourseController::class, 'updateStatus']);
    Route::patch('/courses/{id}/client-location', [CourseController::class, 'updateClientLocation']);
    Route::get('/courses/{id}/client-location', [CourseController::class, 'clientLocation']);
    Route::post('/courses/estimate-price', [CourseController::class, 'estimatePrice']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('utilisateurs', UtilisateurController::class);
        Route::apiResource('notifications', NotificationController::class)->except(['index', 'show']);
        Route::patch('/utilisateurs/{id}/toggle-block', [UtilisateurController::class, 'toggleBlock']);
    });

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});