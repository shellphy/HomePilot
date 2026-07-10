<?php

namespace App\Filament\Resources\Records\Tables;

use App\Models\Record;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('matter.title')
                    ->label('所属征集'),
                TextColumn::make('resident.unit.label')
                    ->label('楼栋')
                    ->placeholder('—'),
                TextColumn::make('resident.room_label')
                    ->label('房号')
                    ->placeholder('—'),
                TextColumn::make('resident.nickname')
                    ->label('昵称')
                    ->searchable(),
                TextColumn::make('resident.wechat_id')
                    ->label('微信号')
                    ->searchable(),
                TextColumn::make('resident.phone')
                    ->label('手机号')
                    ->placeholder('未填')
                    ->searchable(),
                TextColumn::make('answered')
                    ->label('已答')
                    ->state(fn (Record $record): string => count($record->payload['answers'] ?? []).' 题'),
                TextColumn::make('created_at')
                    ->label('登记时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
