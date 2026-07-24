<?php

test('the phone number entry page does not imply an official WeChat login', function () {
    $profileForm = file_get_contents(base_path('miniprogram/pages/profile-form/index.wxml'));

    expect($profileForm)
        ->toContain('open-type="getPhoneNumber"')
        ->toContain('>快捷登录</button>')
        ->not->toContain('微信')
        ->not->toContain('logo-wechat');
});
