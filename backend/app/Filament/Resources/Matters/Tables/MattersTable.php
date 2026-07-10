<?php

namespace App\Filament\Resources\Matters\Tables;

use App\Matters\MatterTypeRegistry;
use App\Models\Matter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MattersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Matter $record): string => $record->typeDef()->label()),
                TextColumn::make('category')
                    ->label('品类')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable(),
                TextColumn::make('state')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Matter $record): string => $record->typeDef()->stateLabel($state)),
                ToggleColumn::make('is_approved')
                    ->label('已公示'),
                TextColumn::make('initiator.nickname')
                    ->label('发起人')
                    ->placeholder('—'),
                TextColumn::make('joins_count')
                    ->label('已参与')
                    ->counts('joins')
                    ->sortable(),
                TextColumn::make('target_count')
                    ->label('目标户数')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('类型')
                    ->options(
                        collect(MatterTypeRegistry::keys())
                            ->mapWithKeys(fn (string $key): array => [$key => MatterTypeRegistry::for($key)->label()])
                            ->all(),
                    ),
                TernaryFilter::make('is_approved')
                    ->label('公示状态')
                    ->trueLabel('已公示')
                    ->falseLabel('待审核'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
