<?php

use App\Models\Matter;
use App\Models\Resident;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;

test('普通权限拒绝不重复写入业务日志', function () {
    Log::spy();

    $matter = Matter::factory()->create();
    $outsider = Resident::factory()->create();
    Sanctum::actingAs($outsider);

    $this->deleteJson("/api/matters/{$matter->id}")->assertForbidden();

    Log::shouldNotHaveReceived('warning');
});

test('404 这类噪音状态码不记日志', function () {
    Log::spy();

    Sanctum::actingAs(Resident::factory()->create());

    $this->getJson('/api/matters/999999')->assertNotFound();

    Log::shouldNotHaveReceived('warning');
});
