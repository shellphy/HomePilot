<?php

use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use App\Models\Resident;
use Laravel\Sanctum\Sanctum;

test('业主可以发起二手闲置，售价/成色/图片落到 payload', function () {
    Sanctum::actingAs(Resident::factory()->inUnit('3栋')->create());

    $response = $this->postJson('/api/matters', [
        'type' => 'secondhand',
        'title' => '九成新婴儿推车',
        'body' => '孩子大了用不上，自提',
        'price' => '150 元',
        'condition' => '9 成新',
        'images' => ['https://cdn.example.com/a.jpg', 'https://cdn.example.com/b.jpg'],
    ])->assertCreated()
        ->assertJsonPath('data.price', '150 元')
        ->assertJsonPath('data.condition', '9 成新');

    $matter = Matter::findOrFail($response->json('data.id'));
    expect($matter->type)->toBe('secondhand')
        ->and($matter->payloadValue('images'))->toHaveCount(2)
        ->and($matter->state)->toBe('open');
});

test('二手闲置在售时开放报名与联系互通，出手/下架后关闭', function () {
    $type = MatterTypeRegistry::for('secondhand');

    $open = Matter::factory()->make(['type' => 'secondhand', 'state' => 'open']);
    $done = Matter::factory()->make(['type' => 'secondhand', 'state' => 'done']);
    $aborted = Matter::factory()->make(['type' => 'secondhand', 'state' => 'aborted']);

    expect($type->allowsJoin($open))->toBeTrue()
        ->and($type->contactsOpen($open))->toBeTrue()
        ->and($type->allowsJoin($done))->toBeFalse()
        ->and($type->allowsJoin($aborted))->toBeFalse()
        ->and($type->stateLabel('aborted'))->toBe('已下架')
        ->and($type->merchantInitiatable())->toBeFalse()
        ->and($type->userInitiatable())->toBeTrue();
});
