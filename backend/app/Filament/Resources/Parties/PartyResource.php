<?php

namespace App\Filament\Resources\Parties;

use App\Filament\Resources\Parties\Pages\CreateParty;
use App\Filament\Resources\Parties\Pages\EditParty;
use App\Filament\Resources\Parties\Pages\ListParties;
use App\Models\Party;
use App\Settings\CommunitySettings;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PartyResource extends Resource
{
    protected static ?string $model = Party::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = '相关方';

    protected static ?string $navigationLabel = '相关方（商家等）';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('类型')
                    ->options([
                        Party::TYPE_MERCHANT => '商家',
                        Party::TYPE_PROPERTY => '物业',
                        Party::TYPE_DEVELOPER => '开发商',
                        Party::TYPE_COMMITTEE => '业委会',
                    ])
                    ->default(Party::TYPE_MERCHANT)
                    ->required(),
                TextInput::make('name')
                    ->label('名称')
                    ->required(),
                TextInput::make('category')
                    ->label('主营品类')
                    ->datalist(fn (): array => app(CommunitySettings::class)->categories)
                    ->default(''),
                Toggle::make('is_listed')
                    ->label('进入公示商家名单')
                    ->helperText('管理员认证后打开——守规矩的商家才有入场券'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Party::TYPE_MERCHANT => '商家',
                        Party::TYPE_PROPERTY => '物业',
                        Party::TYPE_DEVELOPER => '开发商',
                        Party::TYPE_COMMITTEE => '业委会',
                        default => $state,
                    }),
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable(),
                TextColumn::make('category')
                    ->label('主营品类'),
                ToggleColumn::make('is_listed')
                    ->label('已公示'),
                TextColumn::make('members_count')
                    ->label('绑定成员')
                    ->counts('members'),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
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

    public static function getPages(): array
    {
        return [
            'index' => ListParties::route('/'),
            'create' => CreateParty::route('/create'),
            'edit' => EditParty::route('/{record}/edit'),
        ];
    }
}
