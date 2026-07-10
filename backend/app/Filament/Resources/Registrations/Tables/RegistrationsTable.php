<?php

namespace App\Filament\Resources\Registrations\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('resident.unit_label')
                    ->label('楼栋')
                    ->searchable(),
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
                TextColumn::make('layout')
                    ->label('户型'),
                TextColumn::make('decoration_mode')
                    ->label('装修方式'),
                TextColumn::make('interests')
                    ->label('感兴趣品类')
                    ->badge(),
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
