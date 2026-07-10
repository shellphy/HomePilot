<?php

namespace App\Filament\Resources\Records;

use App\Filament\Resources\Records\Pages\ListRecords;
use App\Filament\Resources\Records\Pages\ViewRecord;
use App\Filament\Resources\Records\Schemas\RecordInfolist;
use App\Filament\Resources\Records\Tables\RecordsTable;
use App\Models\Record;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 征集表态只读：各类摸底（装修意向等）的登记明细，管理端只看不改。
 */
class RecordResource extends Resource
{
    protected static ?string $model = Record::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = '征集登记';

    protected static ?string $navigationLabel = '征集登记';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('mode', Record::MODE_REGISTER)
            ->with(['resident.unit', 'matter']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecords::route('/'),
            'view' => ViewRecord::route('/{record}'),
        ];
    }
}
