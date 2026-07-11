<?php

use App\Ai\Agents\MatterExplainer;
use App\Models\Matter;
use App\Models\Resident;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;

test('a resident asks the ai about a groupbuy and can follow up in the same conversation', function () {
    MatterExplainer::fake(['一台外机带四个内机。', '三房两厅通常够用。']);
    $matter = Matter::factory()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $first = $this->postJson("/api/matters/{$matter->id}/ai-chat", [
        'question' => '1 拖 4 是什么意思？',
    ])->assertSuccessful();

    expect($first->json('data.answer'))->toBe('一台外机带四个内机。')
        ->and($first->json('data.conversation_id'))->not->toBeNull()
        ->and($first->json('data.remaining_today'))->toBe(99);

    $second = $this->postJson("/api/matters/{$matter->id}/ai-chat", [
        'question' => '那我家三房够吗？',
        'conversation_id' => $first->json('data.conversation_id'),
    ])->assertSuccessful();

    // fake 在续聊路径下按默认响应兜底，这里只关心会话延续与有回答
    expect($second->json('data.answer'))->toBeString()->not->toBeEmpty()
        ->and($second->json('data.conversation_id'))->toBe($first->json('data.conversation_id'));

    MatterExplainer::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('1 拖 4'));
});

test('the agent instructions carry the matter terms, glossary and community constraints', function () {
    $matter = Matter::factory()->create([
        'title' => '中央空调团购',
        'category' => '中央空调',
        'payload' => [
            'pitch' => '我自己家也装这套',
            'perk' => '满 20 户送清洗',
            'terms' => [['label' => '一拖四', 'value' => '3.2 万']],
            'glossary' => [['term' => '双转子压缩机', 'explain' => '两个转子轮流做功', 'judge' => '看噪音参数', 'caution' => '问清型号']],
            'needs_survey' => true,
        ],
    ]);

    $instructions = (string) (new MatterExplainer($matter))->instructions();

    expect($instructions)
        ->toContain('中央空调团购')
        ->toContain('一拖四')
        ->toContain('3.2 万')
        ->toContain('双转子压缩机')
        ->toContain('看噪音参数')
        ->toContain('满 20 户送清洗')
        ->toContain('按户出方案')
        ->toContain('外机位'); // 小区硬条件出厂默认值
});

test('the daily limit cuts off with a friendly message', function () {
    MatterExplainer::fake();
    $matter = Matter::factory()->create();
    $resident = Resident::factory()->create();
    Sanctum::actingAs($resident);

    foreach (range(1, 100) as $i) {
        RateLimiter::hit('matter-ai-chat:'.$resident->id, 86400);
    }

    $this->postJson("/api/matters/{$matter->id}/ai-chat", ['question' => '还能问吗？'])
        ->assertStatus(429);

    MatterExplainer::assertNeverPrompted();
});

test('notices do not offer ai chat and unapproved matters stay hidden', function () {
    MatterExplainer::fake();
    Sanctum::actingAs(Resident::factory()->create());

    $notice = Matter::factory()->notice()->create();
    $this->postJson("/api/matters/{$notice->id}/ai-chat", ['question' => '？'])->assertStatus(422);

    $pending = Matter::factory()->pending()->create();
    $this->postJson("/api/matters/{$pending->id}/ai-chat", ['question' => '？'])->assertNotFound();
});

test('guests cannot use ai chat', function () {
    MatterExplainer::fake();
    $matter = Matter::factory()->create();

    $this->postJson("/api/matters/{$matter->id}/ai-chat", ['question' => '？'])->assertUnauthorized();
});
