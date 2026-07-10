<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CensusController;
use App\Http\Controllers\Api\JoinController;
use App\Http\Controllers\Api\MatterController;
use App\Http\Controllers\Api\MatterUpdateController;
use App\Http\Controllers\Api\OptionController;
use App\Http\Controllers\Api\PartyController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/options', [OptionController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    // 成员与身份
    Route::get('/me', [ProfileController::class, 'show']);
    Route::put('/me', [ProfileController::class, 'update']);
    Route::post('/me/party', [PartyController::class, 'store']);
    Route::delete('/me/party', [PartyController::class, 'destroy']);

    // 小区概况（户数、入驻数）
    Route::get('/stats', [StatsController::class, 'index']);

    // 事务与表态
    Route::get('/matters', [MatterController::class, 'index']);
    Route::get('/matters/mine', [MatterController::class, 'mine']);
    Route::get('/matters/joined', [MatterController::class, 'joined']);
    Route::get('/matters/{matter}', [MatterController::class, 'show']);
    Route::post('/matters', [MatterController::class, 'store']);
    Route::put('/matters/{matter}', [MatterController::class, 'update']);
    Route::put('/matters/{matter}/state', [MatterController::class, 'updateState']);
    Route::put('/matters/{matter}/deal', [MatterController::class, 'updateDeal']);
    Route::post('/matters/{matter}/join', [JoinController::class, 'store']);
    Route::delete('/matters/{matter}/join', [JoinController::class, 'destroy']);
    Route::put('/matters/{matter}/review', [ReviewController::class, 'store']);
    Route::post('/matters/{matter}/updates', [MatterUpdateController::class, 'store']);
    Route::get('/matters/{matter}/census', [CensusController::class, 'show']);
    Route::put('/matters/{matter}/census', [CensusController::class, 'store']);

    Route::post('/uploads', [UploadController::class, 'store']);
});
