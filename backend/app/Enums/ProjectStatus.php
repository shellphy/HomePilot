<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasColor, HasLabel
{
    case Seeking = 'seeking';

    case Negotiating = 'negotiating';

    case Open = 'open';

    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Seeking => '意向征集',
            self::Negotiating => '谈判中',
            self::Open => '接龙中',
            self::Done => '已成团',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Seeking => 'info',
            self::Negotiating => 'warning',
            self::Open => 'success',
            self::Done => 'gray',
        };
    }
}
