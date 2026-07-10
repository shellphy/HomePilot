<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProgressUpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'progressUpdates';

    protected static ?string $title = '进度更新';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('happened_on')
                    ->label('日期')
                    ->default(now())
                    ->required(),
                Textarea::make('content')
                    ->label('进度内容')
                    ->placeholder('如：水电开槽完成，横平竖直验收通过')
                    ->rows(3)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('content')
            ->defaultSort('happened_on', 'desc')
            ->columns([
                TextColumn::make('happened_on')
                    ->label('日期')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('content')
                    ->label('内容')
                    ->wrap()
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make()->label('发布进度'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
