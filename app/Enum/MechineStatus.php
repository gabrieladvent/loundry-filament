<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
enum MechineStatus: string implements HasColor, HasIcon, HasLabel
{
    case AVAILABLE = 'available';
    case IN_USE = 'in_use';
    case MAINTENANCE = 'maintenance';
    case BROKEN = 'broken';

    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia',
            self::IN_USE => 'Dipakai',
            self::MAINTENANCE => 'Perawatan',
            self::BROKEN => 'Rusak',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AVAILABLE => 'success',
            self::IN_USE => 'warning',
            self::MAINTENANCE => 'gray',
            self::BROKEN => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::AVAILABLE => 'heroicon-o-face-smile',
            self::IN_USE => 'heroicon-o-clock',
            self::MAINTENANCE => 'heroicon-o-wrench-screwdriver',
            self::BROKEN => 'heroicon-o-face-frown',
        };
    }
}
