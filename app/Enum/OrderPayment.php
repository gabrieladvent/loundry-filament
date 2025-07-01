<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderPayment: string implements HasColor, HasLabel
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::UNPAID => 'danger',
            self::PARTIAL => 'warning',
            self::PAID => 'success',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Dibayar',
            self::PARTIAL => 'Sebagian Dibayar',
            self::PAID => 'Lunas',
        };
    }

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
}
