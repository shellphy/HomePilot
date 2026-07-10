<?php

namespace App\Filament\Resources\Records\Pages;

use App\Filament\Resources\Records\RecordResource;
use Filament\Resources\Pages\ListRecords as BaseListRecords;

class ListRecords extends BaseListRecords
{
    protected static string $resource = RecordResource::class;
}
