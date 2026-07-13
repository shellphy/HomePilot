<?php

use App\Models\Matter;
use App\Models\MatterQuestion;
use App\Models\Party;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('a resident asks a question and it shows up pending in the list', function () {
    $matter = Matter::factory()->create();
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋', 'nickname' => '小青']));

    $this->postJson("/api/matters/{$matter->id}/questions", [
        'content' => '内机保修几年？',
    ])->assertCreated();

    $this->getJson("/api/matters/{$matter->id}/questions")
        ->assertSuccessful()
        ->assertJsonPath('data.0.content', '内机保修几年？')
        ->assertJsonPath('data.0.is_mine', true)
        ->assertJsonPath('data.0.answer', null)
        ->assertJsonPath('can_ask', true);
});

test('asking requires a building label on the profile', function () {
    $matter = Matter::factory()->create();
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '']));

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '？？'])
        ->assertJsonValidationErrors(['profile']);
});

test('party identities cannot ask and notices are closed to questions', function () {
    $party = Party::factory()->create();
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋', 'affiliated_party_id' => $party->id]));
    $matter = Matter::factory()->create();

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '？'])->assertForbidden();

    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));
    $notice = Matter::factory()->notice()->create();
    $this->postJson("/api/matters/{$notice->id}/questions", ['content' => '？'])->assertStatus(422);
});

test('rights and aid actions are open to questions', function (string $factoryState) {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));
    $matter = Matter::factory()->{$factoryState}()->create();

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '这次有什么风险？'])->assertCreated();
    $this->getJson("/api/matters/{$matter->id}/questions")->assertJsonPath('can_ask', true);
})->with(['rights', 'aid']);

test('censuses stay closed to questions', function () {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));
    $census = Matter::factory()->create(['type' => 'census', 'state' => 'open']);

    $this->postJson("/api/matters/{$census->id}/questions", ['content' => '？'])->assertStatus(422);
});

test('the matter resource flags whether questions are open', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/'.Matter::factory()->rights()->create()->id)
        ->assertJsonPath('data.supports_questions', true);
    $this->getJson('/api/matters/'.Matter::factory()->notice()->create()->id)
        ->assertJsonPath('data.supports_questions', false);
});

test('neighbors echo a question but not their own, and echo toggles', function () {
    $question = MatterQuestion::factory()->create();

    Sanctum::actingAs(Resident::factory()->create());
    $this->postJson("/api/questions/{$question->id}/echo")
        ->assertSuccessful()
        ->assertJsonPath('data.echoed_by_me', true)
        ->assertJsonPath('data.echo_count', 1);

    $this->postJson("/api/questions/{$question->id}/echo")
        ->assertSuccessful()
        ->assertJsonPath('data.echoed_by_me', false)
        ->assertJsonPath('data.echo_count', 0);

    Sanctum::actingAs($question->asker);
    $this->postJson("/api/questions/{$question->id}/echo")->assertStatus(422);
});

test('the initiator answers with their name and can revise the answer', function () {
    $initiator = Resident::factory()->create(['unit_label' => '5栋', 'nickname' => '老王']);
    $matter = Matter::factory()->for($initiator, 'initiator')->create();
    $question = MatterQuestion::factory()->for($matter)->create();

    Sanctum::actingAs($initiator);

    $this->putJson("/api/questions/{$question->id}/answer", ['content' => '商家承诺整机保修 6 年。'])
        ->assertSuccessful();

    expect($question->refresh())
        ->answer->toBe('商家承诺整机保修 6 年。')
        ->answered_by->toBe($initiator->displayName());

    $firstAnsweredAt = $question->answered_at;
    $this->putJson("/api/questions/{$question->id}/answer", ['content' => '更正：整机 6 年，压缩机 10 年。'])
        ->assertSuccessful();

    // 修改回答不刷新首次回答时间
    expect($question->refresh())
        ->answer->toBe('更正：整机 6 年，压缩机 10 年。')
        ->answered_at->format('Y-m-d H:i:s')->toBe($firstAnsweredAt->format('Y-m-d H:i:s'));
});

test('merchant staff of the signed party answer under the merchant name', function () {
    $merchant = Party::factory()->listed()->create(['name' => '青城中央空调']);
    $staff = Resident::factory()->create(['affiliated_party_id' => $merchant->id]);
    $matter = Matter::factory()->create(['initiator_party_id' => $merchant->id]);
    $question = MatterQuestion::factory()->for($matter)->create();

    Sanctum::actingAs($staff);

    $this->putJson("/api/questions/{$question->id}/answer", ['content' => '外机噪音 52 分贝左右。'])
        ->assertSuccessful();

    expect($question->refresh()->answered_by)->toBe('青城中央空调');
});

test('ordinary residents cannot answer', function () {
    $question = MatterQuestion::factory()->create();
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson("/api/questions/{$question->id}/answer", ['content' => '我猜是 6 年'])
        ->assertForbidden();
});

test('the initiator promotes an answered question into the glossary', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->for($initiator, 'initiator')->create();
    $question = MatterQuestion::factory()->for($matter)->answered('两个转子轮流做功，更省电也更静音。')->create();

    Sanctum::actingAs($initiator);

    $this->postJson("/api/questions/{$question->id}/promote", ['term' => '双转子压缩机'])
        ->assertSuccessful();

    expect($matter->refresh()->payloadValue('glossary'))
        ->toContain(['term' => '双转子压缩机', 'explain' => '两个转子轮流做功，更省电也更静音。']);

    // 同名词条不重复沉淀
    $this->postJson("/api/questions/{$question->id}/promote", ['term' => '双转子压缩机'])
        ->assertStatus(422);
});

test('unanswered questions cannot be promoted', function () {
    $initiator = Resident::factory()->create();
    $matter = Matter::factory()->for($initiator, 'initiator')->create();
    $question = MatterQuestion::factory()->for($matter)->create();

    Sanctum::actingAs($initiator);

    $this->postJson("/api/questions/{$question->id}/promote", ['term' => '词'])->assertStatus(422);
});

test('answered questions rank above pending ones, echoes break ties', function () {
    $matter = Matter::factory()->create();
    $pendingHot = MatterQuestion::factory()->for($matter)->create(['content' => '待回复但很多人同问']);
    $answered = MatterQuestion::factory()->for($matter)->answered()->create(['content' => '已回答']);
    $pendingHot->echoers()->attach(Resident::factory()->count(3)->create());

    Sanctum::actingAs(Resident::factory()->create());

    $response = $this->getJson("/api/matters/{$matter->id}/questions")->assertSuccessful();

    expect($response->json('data.0.content'))->toBe('已回答')
        ->and($response->json('data.1.content'))->toBe('待回复但很多人同问')
        ->and($response->json('data.1.echo_count'))->toBe(3);
});
