<?php

use App\Models\Matter;
use App\Models\MatterQuestion;
use App\Models\Party;
use App\Models\Resident;
use Illuminate\Support\Facades\DB;
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

test('an approved party identity can ask, a pending one cannot', function () {
    $matter = Matter::factory()->create();

    $approved = Party::factory()->listed()->create();
    Sanctum::actingAs(Resident::factory()->create(['affiliated_party_id' => $approved->id]));
    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '？'])->assertCreated();

    $pending = Party::factory()->create();
    Sanctum::actingAs(Resident::factory()->create(['affiliated_party_id' => $pending->id]));
    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '？'])->assertForbidden();
});

test('asking and answering require a building label on the profile', function () {
    $matter = Matter::factory()->create();
    $question = MatterQuestion::factory()->for($matter)->create();
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '']));

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '？'])
        ->assertJsonValidationErrors(['profile']);
    $this->putJson("/api/questions/{$question->id}/answer", ['content' => '答'])
        ->assertJsonValidationErrors(['profile']);
});

test('a blocked resident can neither ask nor answer', function () {
    $matter = Matter::factory()->create();
    $question = MatterQuestion::factory()->for($matter)->create();
    Sanctum::actingAs(Resident::factory()->blocked()->create(['unit_label' => '3栋']));

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '？'])->assertForbidden();
    $this->putJson("/api/questions/{$question->id}/answer", ['content' => '答'])->assertForbidden();
});

test('every matter type is open to questions', function (string $factoryState) {
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));
    $matter = Matter::factory()->{$factoryState}()->create();

    $this->postJson("/api/matters/{$matter->id}/questions", ['content' => '这次有什么风险？'])->assertCreated();
    $this->getJson("/api/matters/{$matter->id}/questions")->assertJsonPath('can_ask', true);
})->with(['rights', 'aid', 'notice']);

test('an admin deletes a question and its echoes', function () {
    $matter = Matter::factory()->create();
    $question = MatterQuestion::factory()->for($matter)->create();
    $question->echoers()->attach(Resident::factory()->create());

    Sanctum::actingAs(Resident::factory()->admin()->create());
    $this->deleteJson("/api/questions/{$question->id}")->assertSuccessful();

    expect(MatterQuestion::find($question->id))->toBeNull()
        ->and(DB::table('matter_question_echoes')->where('matter_question_id', $question->id)->count())->toBe(0);
});

test('an admin deletes only the answer, keeping the question', function () {
    $question = MatterQuestion::factory()->answered('先前的回答')->create();
    Sanctum::actingAs(Resident::factory()->admin()->create());

    $this->deleteJson("/api/questions/{$question->id}/answer")->assertSuccessful();

    expect($question->refresh())
        ->answer->toBeNull()
        ->answered_at->toBeNull()
        ->content->not->toBe('');
});

test('an outsider (not author, not admin) cannot delete a question or an answer', function () {
    $question = MatterQuestion::factory()->answered()->create();
    Sanctum::actingAs(Resident::factory()->create(['unit_label' => '3栋']));

    $this->deleteJson("/api/questions/{$question->id}")->assertForbidden();
    $this->deleteJson("/api/questions/{$question->id}/answer")->assertForbidden();
});

test('the asker can delete their own question', function () {
    $asker = Resident::factory()->create(['unit_label' => '3栋']);
    $question = MatterQuestion::factory()->for($asker, 'asker')->create();
    Sanctum::actingAs($asker);

    $this->deleteJson("/api/questions/{$question->id}")->assertSuccessful();
    expect(MatterQuestion::find($question->id))->toBeNull();
});

test('the answerer can delete their own reply but not the whole question', function () {
    $matter = Matter::factory()->create();
    $question = MatterQuestion::factory()->for($matter)->create();
    $answerer = Resident::factory()->create(['unit_label' => '7栋']);
    Sanctum::actingAs($answerer);
    $this->putJson("/api/questions/{$question->id}/answer", ['content' => '我来答'])->assertSuccessful();

    // 回复本人能删自己的回复
    $this->deleteJson("/api/questions/{$question->id}/answer")->assertSuccessful();
    expect($question->refresh()->answer)->toBeNull();

    // 但删不了别人的整条问题
    $this->deleteJson("/api/questions/{$question->id}")->assertForbidden();
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

test('party staff answer under the party name', function () {
    $merchant = Party::factory()->listed()->create(['name' => '青城中央空调']);
    $staff = Resident::factory()->create(['affiliated_party_id' => $merchant->id]);
    $matter = Matter::factory()->create();
    $question = MatterQuestion::factory()->for($matter)->create();

    Sanctum::actingAs($staff);

    $this->putJson("/api/questions/{$question->id}/answer", ['content' => '外机噪音 52 分贝左右。'])
        ->assertSuccessful();

    expect($question->refresh()->answered_by)->toBe('青城中央空调');
});

test('any resident can answer, signed under their name', function () {
    $question = MatterQuestion::factory()->create();
    $neighbor = Resident::factory()->create(['unit_label' => '7栋', 'nickname' => '小林']);
    Sanctum::actingAs($neighbor);

    $this->putJson("/api/questions/{$question->id}/answer", ['content' => '我这栋是保修 6 年。'])
        ->assertSuccessful();

    expect($question->refresh())
        ->answer->toBe('我这栋是保修 6 年。')
        ->answered_by->toBe($neighbor->displayName())
        ->answered_by_id->toBe($neighbor->id);
});

test('the answerer id is exposed only to admins, for blocking', function () {
    $matter = Matter::factory()->create();
    $question = MatterQuestion::factory()->for($matter)->create();
    $answerer = Resident::factory()->create(['unit_label' => '7栋']);
    Sanctum::actingAs($answerer);
    $this->putJson("/api/questions/{$question->id}/answer", ['content' => '我知道'])->assertSuccessful();

    $this->getJson("/api/matters/{$matter->id}/questions")->assertJsonPath('data.0.answerer_id', null);

    Sanctum::actingAs(Resident::factory()->admin()->create());
    $this->getJson("/api/matters/{$matter->id}/questions")->assertJsonPath('data.0.answerer_id', $answerer->id);
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
