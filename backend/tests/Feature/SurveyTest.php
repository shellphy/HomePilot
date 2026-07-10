<?php

use App\Models\Registration;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('the survey ships its modules and my existing answers', function () {
    $resident = Resident::factory()->create();
    Registration::factory()->for($resident)->create(['answers' => ['cooking' => '几乎每天']]);
    Sanctum::actingAs($resident);

    $this->getJson('/api/survey')
        ->assertSuccessful()
        ->assertJsonPath('modules.0.key', 'family')
        ->assertJsonPath('answers.cooking', '几乎每天');
});

test('answers merge module by module', function () {
    $resident = Resident::factory()->create();
    Registration::factory()->for($resident)->create(['answers' => null]);
    Sanctum::actingAs($resident);

    $this->putJson('/api/survey', ['answers' => ['household_size' => '3 人']])->assertSuccessful();
    $this->putJson('/api/survey', ['answers' => ['cooking' => '几乎每天', 'pets' => ['猫']]])
        ->assertSuccessful()
        ->assertJsonPath('answered', 3);

    expect($resident->registration()->first()->answers)
        ->toHaveKeys(['household_size', 'cooking', 'pets']);
});

test('the survey requires a basic registration first', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson('/api/survey', ['answers' => ['cooking' => '几乎每天']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('answers');
});

test('the survey rejects unknown questions and invalid options', function (array $answers) {
    $resident = Resident::factory()->create();
    Registration::factory()->for($resident)->create();
    Sanctum::actingAs($resident);

    $this->putJson('/api/survey', ['answers' => $answers])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('answers');
})->with([
    '未知问题' => [['favorite_car' => '火箭']],
    '单选给了未知选项' => [['cooking' => '一天八顿']],
    '多选给了字符串' => [['pets' => '猫']],
    '多选含未知选项' => [['pets' => ['猫', '恐龙']]],
]);
