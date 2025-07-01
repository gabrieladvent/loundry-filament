<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum InventoryCategory: string implements HasLabel
{
    case DETERGENT = 'detergent';
    case SOFTENER = 'softener';
    case BLEACH = 'bleach';
    case STARCH = 'starch';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::DETERGENT => 'Deterjen',
            self::SOFTENER => 'Softener',
            self::BLEACH => 'Pemutih',
            self::STARCH => 'Starch',
            self::OTHER => 'Lainnya',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn($case) => [
            $case->value => $case->getLabel()
        ])->toArray();
    }
}
