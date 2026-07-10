<?php

use App\Models\Registration;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'layout' => config('homepilot.layouts')[0],
        'decoration_mode' => config('homepilot.decoration_modes')[0],
        'interests' => [config('homepilot.categories')[0]],
        'unit_label' => '3栋',
        'wechat_id' => 'laoK-2026',
    ], $overrides);
}

test('a resident can submit an intent registration', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson('/api/registration', validRegistrationPayload())->assertCreated();

    expect(Registration::count())->toBe(1);
});

test('resubmitting updates the existing registration instead of creating a second one', function () {
    $resident = Resident::factory()->create();
    Sanctum::actingAs($resident);

    $this->putJson('/api/registration', validRegistrationPayload())->assertCreated();
    $this->putJson('/api/registration', validRegistrationPayload([
        'layout' => config('homepilot.layouts')[1],
    ]))->assertSuccessful();

    expect(Registration::count())->toBe(1)
        ->and($resident->registration()->first()->layout)->toBe(config('homepilot.layouts')[1]);
});

test('contact fields are required and saved on the resident, phone stays optional', function () {
    $resident = Resident::factory()->create(['unit_label' => '', 'wechat_id' => '', 'phone' => '']);
    Sanctum::actingAs($resident);

    $this->putJson('/api/registration', validRegistrationPayload([
        'unit_label' => '7栋',
        'wechat_id' => 'my-wechat',
    ]))->assertCreated();

    expect($resident->refresh()->wechat_id)->toBe('my-wechat')
        ->and($resident->unit_label)->toBe('7栋')
        ->and($resident->phone)->toBe('');

    $this->putJson('/api/registration', validRegistrationPayload([
        'wechat_id' => 'my-wechat',
        'phone' => '13800138000',
    ]))->assertSuccessful();

    expect($resident->refresh()->phone)->toBe('13800138000');
});

test('registration rejects values outside the configured options', function (array $overrides, string $invalidField) {
    Sanctum::actingAs(Resident::factory()->create());

    $this->putJson('/api/registration', validRegistrationPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($invalidField);
})->with([
    '未知户型' => [['layout' => '999㎡ · 十房'], 'layout'],
    '未知装修方式' => [['decoration_mode' => '意念装修'], 'decoration_mode'],
    '空的品类' => [['interests' => []], 'interests'],
    '未知品类' => [['interests' => ['买飞机']], 'interests.0'],
    '缺楼栋号' => [['unit_label' => ''], 'unit_label'],
    '缺微信号' => [['wechat_id' => ''], 'wechat_id'],
    '错误手机号' => [['phone' => '123'], 'phone'],
]);

test('a resident can fetch their own registration', function () {
    $resident = Resident::factory()->create();
    $registration = Registration::factory()->for($resident)->create();
    Sanctum::actingAs($resident);

    $this->getJson('/api/registration')
        ->assertSuccessful()
        ->assertJsonPath('data.layout', $registration->layout);
});

test('fetching the registration before submitting returns null data', function () {
    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/registration')
        ->assertSuccessful()
        ->assertJsonPath('data', null);
});

test('guests cannot touch registrations', function () {
    $this->getJson('/api/registration')->assertUnauthorized();
    $this->putJson('/api/registration', [])->assertUnauthorized();
});
