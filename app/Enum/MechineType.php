<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum MechineType: string implements HasLabel
{
    case WASHING = 'washing';
    case DRYING = 'drying';
    case IRONING = 'ironing';

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
            self::WASHING => 'Mesin Cuci',
            self::DRYING => 'Mesin Pencuci',
            self::IRONING => 'Setrikaan',
        };
    }
}
