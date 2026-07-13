<?php

test('short form values use the original right aligned layout', function () {
    $projectRoot = dirname(__DIR__, 2);
    $styles = file_get_contents($projectRoot.'/miniprogram/app.wxss');

    expect($styles)
        ->toMatch('/\.frow-input\s*\{[^}]*text-align: right;/s')
        ->toMatch('/\.frow-value\s*\{[^}]*text-align: right;/s');
});

test('the matter form exposes consistent inputs and date time controls', function () {
    $projectRoot = dirname(__DIR__, 2);
    $markup = file_get_contents($projectRoot.'/miniprogram/pages/admin/matter-form/index.wxml');
    $styles = file_get_contents($projectRoot.'/miniprogram/pages/admin/matter-form/index.wxss');

    expect($markup)
        ->toContain('class="frow-input matter-form-input"')
        ->toContain('<datetime-field')
        ->toContain('bind:change="onDateTimeChange"')
        ->toContain('placeholder="例如：小区东门活动室"')
        ->and($styles)
        ->toMatch('/\.matter-form-input\s*\{[^}]*border:/s');

    $component = file_get_contents($projectRoot.'/miniprogram/components/datetime-field/index.wxml');
    $componentStyles = file_get_contents($projectRoot.'/miniprogram/components/datetime-field/index.wxss');

    expect($component)
        ->toContain('mode="date"')
        ->toContain('mode="time"')
        ->and($componentStyles)
        ->toMatch('/\.datetime-control\s*\{[^}]*border:/s');
});

test('unfinished census drafts can be resumed and explicitly submitted for review', function () {
    $projectRoot = dirname(__DIR__, 2);
    $schema = file_get_contents($projectRoot.'/miniprogram/pages/admin/census-schema/index.wxml');
    $schemaPage = file_get_contents($projectRoot.'/miniprogram/pages/admin/census-schema/index.js');
    $minePage = file_get_contents($projectRoot.'/miniprogram/pages/mine-matters/index.js');

    expect($schema)
        ->toContain("reviewStatus === 'draft'")
        ->toContain('至少添加一道题')
        ->toContain('bind:tap="submitReview"')
        ->toContain('bind:tap="editBasics"')
        ->and($schemaPage)
        ->toContain('submitMatterReview(this.data.id)')
        ->and($minePage)
        ->toContain("matter.type === 'census' && matter.review_status === 'draft'")
        ->toContain('/pages/admin/census-schema/index?id=');
});
