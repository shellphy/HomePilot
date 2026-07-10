<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Models\Registration;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RegistrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('resident.unit_label')
                    ->label('楼栋'),
                TextEntry::make('resident.nickname')
                    ->label('昵称'),
                TextEntry::make('resident.wechat_id')
                    ->label('微信号'),
                TextEntry::make('resident.phone')
                    ->label('手机号')
                    ->placeholder('未填'),
                TextEntry::make('layout')
                    ->label('户型'),
                TextEntry::make('decoration_mode')
                    ->label('装修方式'),
                TextEntry::make('interests')
                    ->label('感兴趣品类')
                    ->badge()
                    ->columnSpanFull(),
                TextEntry::make('answers')
                    ->label('进阶问卷')
                    ->state(function (Registration $record): array {
                        /** @var array<int, array{key: string, title: string, questions: array<int, array{key: string, text: string, type: string, options: array<int, string>}>}> $modules */
                        $modules = config('homepilot.survey');

                        $questions = collect($modules)
                            ->flatMap(fn (array $module): array => $module['questions'])
                            ->keyBy('key');

                        return collect($record->answers ?? [])
                            ->map(function (string|array $value, string $key) use ($questions): string {
                                $text = $questions[$key]['text'] ?? $key;
                                $answer = is_array($value) ? implode('、', $value) : $value;

                                return "{$text}　{$answer}";
                            })
                            ->values()
                            ->all();
                    })
                    ->listWithLineBreaks()
                    ->placeholder('未填写')
                    ->columnSpanFull(),
                TextEntry::make('updated_at')
                    ->label('最后更新')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-'),
            ]);
    }
}
