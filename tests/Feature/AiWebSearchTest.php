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

test('the deepseek-anthropic provider disables native structured output so web search + JSON works', function () {
    $config = config('ai.providers.deepseek-anthropic');

    // 借 anthropic driver 拿服务端 web search；DeepSeek 兼容层没有 output_config，
    // 必须关掉原生结构化输出，回退到 text→JSON 解析。
    expect($config['driver'])->toBe('anthropic')
        ->and($config['url'])->toContain('deepseek.com/anthropic')
        ->and($config['use_native_structured_output'])->toBeFalse();
});

test('structured agents instruct the model to end with pure JSON', function (string $class) {
    // 联网后 DeepSeek 以 text 收尾、不调用合成输出工具，指令必须要求最终只吐 JSON，
    // 否则 SDK 的 text→JSON 回退无从解析。
    $instructions = (string) makeAgent($class)->instructions();

    expect($instructions)->toContain('JSON');
})->with([
    GlossaryDrafter::class,
    CensusReportGenerator::class,
]);

test('the conversational agent stays natural language, not JSON', function () {
    // 答疑输出自然语言，不能被结构化指令污染。
    $instructions = (string) (new MatterExplainer(Matter::factory()->create()))->instructions();

    expect($instructions)->not->toContain('只包含一个符合所需字段结构的 JSON');
});
