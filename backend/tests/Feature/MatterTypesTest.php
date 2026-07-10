<?php

use App\Matters\MatterTypeRegistry;
use App\Models\Matter;

test('the registry knows its types and rejects unknown ones', function () {
    expect(MatterTypeRegistry::keys())->toContain('groupbuy', 'notice');
    expect(fn () => MatterTypeRegistry::for('carpool'))->toThrow(InvalidArgumentException::class);
});

test('each type declares a state machine whose first state is the initial one', function (string $key) {
    $type = MatterTypeRegistry::for($key);

    expect($type->states())->not->toBeEmpty()
        ->and($type->initialState())->toBe(array_key_first($type->states()))
        ->and($type->stateLabel($type->initialState()))->not->toBe('');
})->with(fn (): array => MatterTypeRegistry::keys());

test('groupbuy sorts open first and done last, notices pin above everything', function () {
    $type = MatterTypeRegistry::for('groupbuy');

    $open = Matter::factory()->open()->make();
    $done = Matter::factory()->done()->make();
    $notice = Matter::factory()->notice()->make();

    expect($type->sortWeight($open))->toBeLessThan($type->sortWeight($done))
        ->and(MatterTypeRegistry::for('notice')->sortWeight($notice))->toBeLessThan($type->sortWeight($open));
});

test('archived notices leave the feed while published ones stay', function () {
    $noticeType = MatterTypeRegistry::for('notice');

    expect($noticeType->visibleInList(Matter::factory()->notice()->make()))->toBeTrue()
        ->and($noticeType->visibleInList(Matter::factory()->notice()->make(['state' => 'archived'])))->toBeFalse();
});
