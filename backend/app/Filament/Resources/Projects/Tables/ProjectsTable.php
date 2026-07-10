<?php

namespace App\Filament\Resources\Projects\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')
                    ->label('品类')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge(),
                ToggleColumn::make('is_approved')
                    ->label('已上架'),
                TextColumn::make('initiator.nickname')
                    ->label('发起人')
                    ->placeholder('—'),
                TextColumn::make('signups_count')
                    ->label('已报名')
                    ->counts('signups')
                    ->sortable(),
                TextColumn::make('target_households')
                    ->label('目标户数')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('上架状态')
                    ->trueLabel('已上架')
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
