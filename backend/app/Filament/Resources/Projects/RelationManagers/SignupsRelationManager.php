<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SignupsRelationManager extends RelationManager
{
    protected static string $relationship = 'signups';

    protected static ?string $title = '报名名单';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('resident.unit_label')
                    ->label('楼栋房号')
                    ->searchable(),
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
