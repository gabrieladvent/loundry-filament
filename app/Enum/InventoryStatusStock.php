<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InventoryStatusStock: string implements HasColor, HasIcon, HasLabel
{
    case OUT_OF_STOCK = 'out_of_stock';
    case LOW_STOCK = 'low_stock';
    case NORMAL_STOCK = 'normal_stock';

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->getLabel()];
        })->toArray();
    }

    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::OUT_OF_STOCK => 'Stok Habis',
            self::LOW_STOCK => 'Stok Rendah',
            self::NORMAL_STOCK => 'Stok Aman',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OUT_OF_STOCK => 'danger',
            self::LOW_STOCK => 'warning',
            self::NORMAL_STOCK => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::OUT_OF_STOCK => 'heroicon-o-x-circle',
            self::LOW_STOCK => 'heroicon-o-exclamation-circle',
            self::NORMAL_STOCK => 'heroicon-o-check-circle',
        };
    }
}
