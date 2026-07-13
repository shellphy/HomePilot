<?php

test('the insights card only offers accessible data and labels participation rate clearly', function () {
    $projectRoot = dirname(__DIR__, 2);
    $markup = file_get_contents($projectRoot.'/miniprogram/pages/insights/index.wxml');
    $page = file_get_contents($projectRoot.'/miniprogram/pages/insights/index.js');

    expect($markup)
        ->toContain('wx:if="{{item.aggregatesVisible}}" class="fcard-cta"')
        ->toContain('{{item.registered}} / {{item.totalResidents}}')
        ->toContain('{{item.participationRate}}%')
        ->toContain('data-visible="{{item.aggregatesVisible}}"')
        ->and($page)
        ->toContain('hasParticipationRate: stats.residents > 0')
        ->toContain('totalResidents: stats.residents')
        ->toContain('participationRate: stats.residents')
        ->toContain('if (!visible) return;');
});
