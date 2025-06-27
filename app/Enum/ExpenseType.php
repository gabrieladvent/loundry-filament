<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;


enum ExpenseType: string implements HasLabel
{
    case DETERJEN = 'detergent';
    case ELECTRICITY = 'electricity';
    case WATER = 'water';
    case MAINTENANCE = 'maintenance';
    case SALARY = 'salary';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->getLabel()];
        })->toArray();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DETERJEN => 'Deterjen',
            self::ELECTRICITY => 'Listrik',
            self::WATER => 'Air',
            self::MAINTENANCE => 'Perawatan',
            self::SALARY => 'Gaji',
            self::OTHER => 'Lainnya',
        };
    }
}
