<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case WASHING = 'washing';
    case DRYING = 'drying';
    case IRONING = 'ironing';
    case READY = 'ready';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

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
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'Dalam Proses',
            self::WASHING => 'Mencuci',
            self::DRYING => 'Mengeringkan',
            self::IRONING => 'Setrika',
            self::READY => 'Siap',
            self::DELIVERED => 'Dikirim',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::IN_PROGRESS => 'warning',
            self::WASHING => 'warning',
            self::DRYING => 'warning',
            self::IRONING => 'warning',
            self::READY => 'warning',
            self::DELIVERED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
