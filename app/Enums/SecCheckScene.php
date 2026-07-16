<?php

namespace App\Enums;

/**
 * 微信内容安全 msgSecCheck 的场景枚举：决定微信按哪套尺度判违规。
 * 值为微信固定约定，不可改。
 */
enum SecCheckScene: int
{
    case Profile = 1;
    case Comment = 2;
    case Forum = 3;
    case SocialLog = 4;
}
