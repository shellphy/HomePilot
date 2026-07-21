<?php

use App\Http\Controllers\Api\Admin\AdminBlockController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\MatterAdminController;
use App\Http\Controllers\Api\Admin\PartyAdminController;
use App\Http\Controllers\Api\Admin\SettingAdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CensusController;
use App\Http\Controllers\Api\CensusOverviewController;
use App\Http\Controllers\Api\CensusReportController;
use App\Http\Controllers\Api\GlossaryDraftController;
use App\Http\Controllers\Api\JoinController;
use App\Http\Controllers\Api\MatterAiChatController;
use App\Http\Controllers\Api\MatterController;
use App\Http\Controllers\Api\MatterQuestionController;
use App\Http\Controllers\Api\MatterUpdateController;
use App\Http\Controllers\Api\OptionController;
use App\Http\Controllers\Api\PartyController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TodoController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::get('/options', [OptionController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    // 成员
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [ProfileController::class, 'show']);
    Route::put('/me', [ProfileController::class, 'update']);
    Route::post('/me/phone', [ProfileController::class, 'resolvePhone'])->middleware('throttle:wechat-phone');
    Route::post('/me/seen', [ProfileController::class, 'markSeen']);
    Route::get('/me/todos', [TodoController::class, 'index']);
    Route::post('/me/party', [PartyController::class, 'store'])->middleware('not_blocked');
    Route::delete('/me/party', [PartyController::class, 'destroy']);
    Route::get('/parties', [PartyController::class, 'index']);
    Route::get('/parties/{party}', [PartyController::class, 'show']);

    // 小区概况
    Route::get('/stats', [StatsController::class, 'index']);

    // 事项
    Route::get('/matters', [MatterController::class, 'index']);
    Route::get('/censuses/overview', CensusOverviewController::class);
    Route::get('/matters/mine', [MatterController::class, 'mine']);
    Route::get('/matters/joined', [MatterController::class, 'joined']);
    Route::get('/matters/{matter}', [MatterController::class, 'show']);
    Route::post('/matters/{matter}/seen', [MatterController::class, 'markSeen']);
    Route::post('/matters', [MatterController::class, 'store'])->middleware('not_blocked');
    Route::post('/matters/{matter}/submit-review', [MatterController::class, 'submitReview'])->middleware('not_blocked');
    Route::put('/matters/{matter}', [MatterController::class, 'update'])->middleware('not_blocked');
    Route::delete('/matters/{matter}', [MatterController::class, 'destroy'])->middleware('not_blocked');
    Route::put('/matters/{matter}/state', [MatterController::class, 'updateState'])->middleware('not_blocked');
    Route::put('/matters/{matter}/participants/{stance}', [MatterController::class, 'updateParticipant'])->middleware('not_blocked');
    Route::put('/matters/{matter}/deal', [MatterController::class, 'updateDeal'])->middleware('not_blocked');
    Route::post('/matters/{matter}/join', [JoinController::class, 'store'])->middleware('not_blocked');
    Route::delete('/matters/{matter}/join', [JoinController::class, 'destroy']);
    Route::put('/matters/{matter}/review', [ReviewController::class, 'store'])->middleware('not_blocked');
    Route::post('/matters/{matter}/updates', [MatterUpdateController::class, 'store'])->middleware('not_blocked');
    Route::get('/matters/{matter}/census', [CensusController::class, 'show']);
    Route::put('/matters/{matter}/census', [CensusController::class, 'store'])->middleware('not_blocked');
    Route::put('/matters/{matter}/census/consent', [CensusController::class, 'consent']);
    Route::get('/matters/{matter}/census-report', [CensusReportController::class, 'show'])->middleware('feature:ai.census_report');
    Route::post('/matters/{matter}/census-report', [CensusReportController::class, 'store'])
        ->middleware(['not_blocked', 'feature:ai.census_report']);
    Route::get('/matters/{matter}/census-consented', [CensusController::class, 'consented']);

    // AI 功能
    Route::post('/glossary/draft', [GlossaryDraftController::class, 'store'])
        ->middleware(['not_blocked', 'feature:ai.glossary_draft']);

    Route::post('/matters/{matter}/ai-chat', [MatterAiChatController::class, 'store'])
        ->middleware(['not_blocked', 'feature:ai.chat']);

    // 公开问答
    Route::get('/matters/{matter}/questions', [MatterQuestionController::class, 'index']);
    Route::post('/matters/{matter}/questions', [MatterQuestionController::class, 'store'])->middleware('not_blocked');
    Route::post('/questions/{question}/echo', [MatterQuestionController::class, 'echo'])->middleware('not_blocked');
    Route::put('/questions/{question}/answer', [MatterQuestionController::class, 'answer'])->middleware('not_blocked');
    Route::post('/questions/{question}/promote', [MatterQuestionController::class, 'promote'])->middleware('not_blocked');
    Route::delete('/questions/{question}/answer', [MatterQuestionController::class, 'destroyAnswer']);
    Route::delete('/questions/{question}', [MatterQuestionController::class, 'destroy']);

    Route::post('/uploads', [UploadController::class, 'store'])->middleware(['not_blocked', 'throttle:uploads']);

    // 管理端
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/matters', [MatterAdminController::class, 'index']);
        Route::put('/matters/{matter}/approve', [MatterAdminController::class, 'approve']);
        Route::get('/parties', [PartyAdminController::class, 'index']);
        Route::put('/parties/{party}', [PartyAdminController::class, 'update']);
        Route::get('/settings', [SettingAdminController::class, 'show']);
        Route::put('/settings', [SettingAdminController::class, 'update']);
        Route::get('/blocks', [AdminBlockController::class, 'index']);
        Route::post('/blocks', [AdminBlockController::class, 'store']);
        Route::delete('/blocks/{resident}', [AdminBlockController::class, 'destroy']);
    });

    // 超级管理端
    Route::middleware('super_admin')->prefix('admin')->group(function () {
        Route::get('/admins', [AdminUserController::class, 'index']);
        Route::get('/admins/candidate', [AdminUserController::class, 'candidate']);
        Route::post('/admins', [AdminUserController::class, 'store']);
        Route::delete('/admins/{resident}', [AdminUserController::class, 'destroy']);
    });
});
