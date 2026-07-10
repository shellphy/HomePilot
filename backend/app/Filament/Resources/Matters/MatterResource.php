<?php

namespace App\Filament\Resources\Matters;

use App\Filament\Resources\Matters\Pages\CreateMatter;
use App\Filament\Resources\Matters\Pages\EditMatter;
use App\Filament\Resources\Matters\Pages\ListMatters;
use App\Filament\Resources\Matters\RelationManagers\JoinsRelationManager;
use App\Filament\Resources\Matters\RelationManagers\UpdatesRelationManager;
use App\Filament\Resources\Matters\Schemas\MatterForm;
use App\Filament\Resources\Matters\Tables\MattersTable;
use App\Models\Matter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MatterResource extends Resource
{
    protected static ?string $model = Matter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $modelLabel = '社区事务';

    protected static ?string $navigationLabel = '社区事务';

    public static function form(Schema $schema): Schema
    {
        return MatterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MattersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            JoinsRelationManager::class,
            UpdatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMatters::route('/'),
            'create' => CreateMatter::route('/create'),
            'edit' => EditMatter::route('/{record}/edit'),
        ];
    }
}
