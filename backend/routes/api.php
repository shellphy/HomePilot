<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OptionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgressUpdateController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\SignupController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SurveyController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/options', [OptionController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [ProfileController::class, 'show']);
    Route::put('/me', [ProfileController::class, 'update']);

    Route::get('/registration', [RegistrationController::class, 'show']);
    Route::put('/registration', [RegistrationController::class, 'store']);

    Route::get('/survey', [SurveyController::class, 'show']);
    Route::put('/survey', [SurveyController::class, 'store']);

    Route::get('/stats', [StatsController::class, 'index']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/mine', [ProjectController::class, 'mine']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::put('/projects/{project}/status', [ProjectController::class, 'updateStatus']);
    Route::post('/projects/{project}/signup', [SignupController::class, 'store']);
    Route::delete('/projects/{project}/signup', [SignupController::class, 'destroy']);
    Route::post('/projects/{project}/progress', [ProgressUpdateController::class, 'store']);
    Route::post('/uploads', [UploadController::class, 'store']);
});
