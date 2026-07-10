<?php

namespace App\Filament\Resources\Matters\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JoinsRelationManager extends RelationManager
{
    protected static string $relationship = 'joins';

    protected static ?string $title = '接龙名单';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('resident.unit.label')
                    ->label('楼栋')
                    ->placeholder('—'),
                TextColumn::make('resident.room_label')
                    ->label('房号')
                    ->placeholder('—'),
                TextColumn::make('resident.nickname')
                    ->label('昵称')
                    ->searchable(),
                TextColumn::make('resident.phone')
                    ->label('手机号'),
                TextColumn::make('created_at')
                    ->label('报名时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()->label('移除'),
            ]);
    }
}
