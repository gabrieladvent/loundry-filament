<?php

namespace App\Enum;

use Filament\Support\Contracts\HasLabel;

enum GenderType: string implements HasLabel
{
    case MALE = 'male';
    case FEMALE = 'female';

    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::MALE => 'Laki-laki',
            self::FEMALE => 'Perempuan',
        };
    }
}
