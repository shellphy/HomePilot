<?php

use App\Http\Controllers\Api\Admin\MatterAdminController;
use App\Http\Controllers\Api\Admin\PartyAdminController;
use App\Http\Controllers\Api\Admin\SettingAdminController;
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
    Route::post('/me/phone', [ProfileController::class, 'updatePhone']);
    Route::post('/me/party', [PartyController::class, 'store']);
    Route::delete('/me/party', [PartyController::class, 'destroy']);
    Route::get('/parties', [PartyController::class, 'index']);

    // 小区概况（户数、入驻数）
    Route::get('/stats', [StatsController::class, 'index']);

    // 事项与表态
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

    // 管理端（管理员=被授权的成员，php artisan admin:grant）：审核、发布、明细、认证、设置
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/matters', [MatterAdminController::class, 'index']);
        Route::post('/matters', [MatterAdminController::class, 'store']);
        Route::get('/matters/{matter}', [MatterAdminController::class, 'show']);
        Route::put('/matters/{matter}', [MatterAdminController::class, 'update']);
        Route::put('/matters/{matter}/approve', [MatterAdminController::class, 'approve']);
        Route::delete('/matters/{matter}', [MatterAdminController::class, 'destroy']);
        Route::get('/matters/{matter}/registrations', [MatterAdminController::class, 'registrations']);
        Route::get('/parties', [PartyAdminController::class, 'index']);
        Route::put('/parties/{party}', [PartyAdminController::class, 'update']);
        Route::get('/settings', [SettingAdminController::class, 'show']);
        Route::put('/settings', [SettingAdminController::class, 'update']);
    });
});
