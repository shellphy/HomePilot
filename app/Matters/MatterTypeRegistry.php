<?php

namespace App\Matters;

use InvalidArgumentException;

class MatterTypeRegistry
{
    /** @var array<string, class-string<MatterType>> */
    private const TYPES = [
        'groupbuy' => GroupbuyType::class,
        'census' => CensusType::class,
        'activity' => ActivityType::class,
        'aid' => AidType::class,
        'secondhand' => SecondhandType::class,
        'rights' => RightsType::class,
        'notice' => NoticeType::class,
    ];

    /** @var array<string, MatterType> */
    private static array $instances = [];

    public static function for(string $key): MatterType
    {
        if (! isset(self::TYPES[$key])) {
            throw new InvalidArgumentException("未知的事项类型：{$key}");
        }

        return self::$instances[$key] ??= new (self::TYPES[$key]);
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }
}
