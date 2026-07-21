<?php

namespace Tests;

use App\Services\WeChat;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->partialMock(WeChat::class, function (MockInterface $mock): void {
            $mock->shouldReceive('msgSecCheck')->andReturnTrue()->byDefault();
        });
    }
}
