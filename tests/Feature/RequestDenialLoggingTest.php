<?php

use App\Models\Matter;
use App\Models\Resident;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

/** 被拒绝的请求全靠 bootstrap/app.php 的 stopIgnoring 才进得了日志，守住这层机制。 */
test('被拒绝的请求会留下带上下文的日志', function () {
    Log::spy();

    $matter = Matter::factory()->create();
    $outsider = Resident::factory()->create();
    Sanctum::actingAs($outsider);

    $this->deleteJson("/api/matters/{$matter->id}")->assertForbidden();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === '请求被拒绝'
            && $context['status'] === 403
            && $context['method'] === 'DELETE'
            && $context['resident_id'] === $outsider->id);
});

test('404 这类噪音状态码不记日志', function () {
    Log::spy();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/999999')->assertNotFound();

    Log::shouldNotHaveReceived('warning');
});
