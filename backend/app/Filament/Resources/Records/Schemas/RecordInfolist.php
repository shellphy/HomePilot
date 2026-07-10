<?php

namespace App\Filament\Resources\Records\Schemas;

use App\Models\Record;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('matter.title')
                    ->label('所属征集'),
                TextEntry::make('resident.unit.label')
                    ->label('楼栋')
                    ->placeholder('—'),
                TextEntry::make('resident.nickname')
                    ->label('昵称'),
                TextEntry::make('resident.wechat_id')
                    ->label('微信号'),
                TextEntry::make('resident.phone')
                    ->label('手机号')
                    ->placeholder('未填'),
                TextEntry::make('payload.answers')
                    ->label('答卷')
                    ->state(function (Record $record): array {
                        // 题目文本来自所属征集事务的 schema——本视图对任何征集通用
                        $questions = collect($record->matter?->payloadValue('modules', []) ?? [])
                            ->flatMap(fn (array $module): array => $module['questions'] ?? [])
                            ->keyBy('key');

                        return collect($record->payload['answers'] ?? [])
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
