<?php

use App\Ai\Agents\CensusReportGenerator;
use App\Ai\Agents\GlossaryDrafter;
use App\Ai\Agents\MatterExplainer;
use App\Models\Matter;
use Laravel\Ai\Attributes\Provider as ProviderAttribute;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Providers\Tools\WebSearch;

/** 需要构造依赖的 agent 用工厂造，无依赖的直接 new。 */
function makeAgent(string $class): object
{
    return $class === MatterExplainer::class
        ? new MatterExplainer(Matter::factory()->create())
        : new $class;
}

test('every AI agent is wired for web search', function (string $class) {
    $agent = makeAgent($class);

    expect($agent)->toBeInstanceOf(HasTools::class);

    $tools = collect($agent->tools());
    expect($tools)->toHaveCount(1)
        ->and($tools->first())->toBeInstanceOf(WebSearch::class);
})->with([
    GlossaryDrafter::class,
    CensusReportGenerator::class,
    MatterExplainer::class,
]);

test('every AI agent routes through the deepseek-anthropic provider', function (string $class) {
    $provider = (new ReflectionClass($class))
        ->getAttributes(ProviderAttribute::class)[0]
        ->newInstance()
        ->value;

    expect($provider)->toBe('deepseek-anthropic');
})->with([
    GlossaryDrafter::class,
    CensusReportGenerator::class,
    MatterExplainer::class,
]);

test('the deepseek-anthropic provider borrows the anthropic driver for server-side web search', function () {
    $config = config('ai.providers.deepseek-anthropic');

    expect($config['driver'])->toBe('anthropic')
        ->and($config['url'])->toContain('deepseek.com/anthropic');
});

test('the drafter rewrites the initiator draft rather than generating from scratch', function () {
    // 发起人先手填，AI 只改写；指令须体现「改写草稿」而非套模板生成。
    $instructions = (string) (new GlossaryDrafter)->instructions();

    expect($instructions)->toContain('改写')
        ->and($instructions)->toContain('草稿');
});
