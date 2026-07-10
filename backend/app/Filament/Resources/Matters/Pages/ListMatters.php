<?php

namespace App\Filament\Resources\Matters\Pages;

use App\Filament\Resources\Matters\MatterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMatters extends ListRecords
{
    protected static string $resource = MatterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
