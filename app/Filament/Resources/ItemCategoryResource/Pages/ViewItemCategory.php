<?php

namespace App\Filament\Resources\ItemCategoryResource\Pages;

use App\Filament\Resources\ItemCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewItemCategory extends ViewRecord
{
    protected static string $resource = ItemCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
