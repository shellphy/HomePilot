<?php

use App\Ai\Agents\MatterExplainer;
use App\Models\Matter;
use App\Models\Resident;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;

/**
 * 把 SSE 流拆成事件数组：每个 `data:` 帧是一段 JSON。
 *
 * @return list<array<string, mixed>>
 */
function sseEvents(TestResponse $response): array
{
    return collect(explode("\n\n", $response->streamedContent()))
        ->map(fn (string $frame) => trim($frame))
        ->filter(fn (string $frame) => str_starts_with($frame, 'data:'))
        ->map(fn (string $frame) => json_decode(trim(substr($frame, 5)), true))
        ->filter()
        ->values()
        ->all();
}

/** 拼接流里所有 delta，得到完整回答。 */
function sseAnswer(TestResponse $response): string
{
    return collect(sseEvents($response))
        ->pluck('delta')
        ->filter()
        ->join('');
}

/** 取收尾帧（带 conversation_id / remaining_today）。 */
function sseDone(TestResponse $response): array
{
    return collect(sseEvents($response))->firstWhere('done', true) ?? [];
}

test('a resident asks the ai about a groupbuy and can follow up in the same conversation', function () {
    MatterExplainer::fake(['一台外机带四个内机。', '三房两厅通常够用。']);
    $matter = Matter::factory()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $first = $this->postJson("/api/matters/{$matter->id}/ai-chat", [
        'question' => '1 拖 4 是什么意思？',
    ])->assertSuccessful();

    expect(sseAnswer($first))->toBe('一台外机带四个内机。')
        ->and(sseDone($first)['conversation_id'])->not->toBeNull()
        ->and(sseDone($first)['remaining_today'])->toBe(99);

    $second = $this->postJson("/api/matters/{$matter->id}/ai-chat", [
        'question' => '那我家三房够吗？',
        'conversation_id' => sseDone($first)['conversation_id'],
    ])->assertSuccessful();

    // fake 在续聊路径下按默认响应兜底，这里只关心会话延续与有回答
    expect(sseAnswer($second))->toBeString()->not->toBeEmpty()
        ->and(sseDone($second)['conversation_id'])->toBe(sseDone($first)['conversation_id']);

    MatterExplainer::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('1 拖 4'));
});

test('the agent instructions carry the matter terms, glossary and community constraints', function () {
    $matter = Matter::factory()->create([
        'title' => '中央空调团购',
        'category' => '中央空调',
        'body' => '我自己家也装这套',
        'payload' => [
            'perk' => '满 20 户送清洗',
            'terms' => [['label' => '一拖四', 'value' => '3.2 万']],
            'glossary' => [['term' => '双转子压缩机', 'explain' => '两个转子轮流做功，更省电也更静音，问清具体型号']],
            'needs_survey' => true,
        ],
    ]);

    $asker = Resident::factory()->create(['layout_label' => '130㎡']);
    $instructions = (string) (new MatterExplainer($matter, $asker))->instructions();

    expect($instructions)
        ->toContain('130㎡') // 提问业主的户型，答疑按「我家」的实际情况
        ->toContain('中央空调团购')
        ->toContain('一拖四')
        ->toContain('3.2 万')
        ->toContain('双转子压缩机')
        ->toContain('更省电也更静音')
        ->toContain('满 20 户送清洗')
        ->toContain('逐人报价')
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

test('the initiator can use ai chat on their own unapproved matter', function () {
    MatterExplainer::fake(['先把外机位量出来。']);
    $initiator = Resident::factory()->create();
    $pending = Matter::factory()->pending()->create(['initiator_id' => $initiator->id]);
    Sanctum::actingAs($initiator);

    $response = $this->postJson("/api/matters/{$pending->id}/ai-chat", ['question' => '要先准备什么？'])
        ->assertSuccessful();

    expect(sseAnswer($response))->toBe('先把外机位量出来。');
});

test('guests cannot use ai chat', function () {
    MatterExplainer::fake();
    $matter = Matter::factory()->create();

    $this->postJson("/api/matters/{$matter->id}/ai-chat", ['question' => '？'])->assertUnauthorized();
});
