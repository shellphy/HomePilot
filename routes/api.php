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
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/options', [OptionController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    // 成员与身份
    Route::get('/me', [ProfileController::class, 'show']);
    Route::put('/me', [ProfileController::class, 'update']);
    Route::post('/me/phone', [ProfileController::class, 'resolvePhone']);
    Route::post('/me/seen', [ProfileController::class, 'markSeen']);
    Route::post('/me/party', [PartyController::class, 'store']);
    Route::delete('/me/party', [PartyController::class, 'destroy']);
    Route::get('/parties', [PartyController::class, 'index']);
    Route::get('/parties/{party}', [PartyController::class, 'show']);

    // 小区概况（户数、入驻数）
    Route::get('/stats', [StatsController::class, 'index']);

    // 事项与表态
    Route::get('/matters', [MatterController::class, 'index']);
    Route::get('/censuses/overview', CensusOverviewController::class);
    Route::get('/matters/mine', [MatterController::class, 'mine']);
    Route::get('/matters/joined', [MatterController::class, 'joined']);
    Route::get('/matters/{matter}', [MatterController::class, 'show']);
    Route::post('/matters/{matter}/seen', [MatterController::class, 'markSeen']);
    Route::post('/matters', [MatterController::class, 'store']);
    Route::post('/matters/{matter}/submit-review', [MatterController::class, 'submitReview']);
    Route::put('/matters/{matter}', [MatterController::class, 'update']);
    Route::delete('/matters/{matter}', [MatterController::class, 'destroy']);
    Route::put('/matters/{matter}/state', [MatterController::class, 'updateState']);
    Route::put('/matters/{matter}/participants/{stance}', [MatterController::class, 'updateParticipant']);
    Route::put('/matters/{matter}/deal', [MatterController::class, 'updateDeal']);
    Route::post('/matters/{matter}/join', [JoinController::class, 'store']);
    Route::delete('/matters/{matter}/join', [JoinController::class, 'destroy']);
    Route::put('/matters/{matter}/review', [ReviewController::class, 'store']);
    Route::post('/matters/{matter}/updates', [MatterUpdateController::class, 'store']);
    Route::get('/matters/{matter}/census', [CensusController::class, 'show']);
    Route::put('/matters/{matter}/census', [CensusController::class, 'store']);
    // 「让发起者看到我的问卷」授权开关：在「查看我的问卷」页冷静态设置
    Route::put('/matters/{matter}/census/consent', [CensusController::class, 'consent']);
    Route::get('/matters/{matter}/census-report', [CensusReportController::class, 'show']);
    Route::post('/matters/{matter}/census-report', [CensusReportController::class, 'store'])->middleware('throttle:10,1');
    // 发起者视图：主动勾选授权的参与者明细（非 admin，授权收窄到发起者本人）
    Route::get('/matters/{matter}/census-consented', [CensusController::class, 'consented']);

    // 「买前必懂」AI 起草（发起/编辑团购表单用，草稿经人工校订后随事项提交）
    Route::post('/glossary/draft', [GlossaryDraftController::class, 'store']);

    // 业主侧 AI 答疑：带事项上下文的多轮对话
    Route::post('/matters/{matter}/ai-chat', [MatterAiChatController::class, 'store']);

    // 「大家都在问」：公开问答（提问/同问/负责方回答/沉淀为买前必懂）
    Route::get('/matters/{matter}/questions', [MatterQuestionController::class, 'index']);
    Route::post('/matters/{matter}/questions', [MatterQuestionController::class, 'store']);
    Route::post('/questions/{question}/echo', [MatterQuestionController::class, 'echo']);
    Route::put('/questions/{question}/answer', [MatterQuestionController::class, 'answer']);
    Route::post('/questions/{question}/promote', [MatterQuestionController::class, 'promote']);
    Route::delete('/questions/{question}/answer', [MatterQuestionController::class, 'destroyAnswer']); // 管理员只删回复
    Route::delete('/questions/{question}', [MatterQuestionController::class, 'destroy']); // 管理员删整条

    Route::post('/uploads', [UploadController::class, 'store']);

    // 管理端（管理员=被授权的成员，php artisan admin:grant）：只有审核类功能——事项审核、相关方认证、社区设置
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

    // 超级管理端（is_super_admin）：应用内增减管理员，替代纯 CLI
    Route::middleware('super_admin')->prefix('admin')->group(function () {
        Route::get('/admins', [AdminUserController::class, 'index']);
        Route::get('/admins/candidate', [AdminUserController::class, 'candidate']);
        Route::post('/admins', [AdminUserController::class, 'store']);
        Route::delete('/admins/{resident}', [AdminUserController::class, 'destroy']);
    });
});
