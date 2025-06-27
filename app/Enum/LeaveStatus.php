<?php

namespace App\Enum;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LeaveStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case WAITING_APPROVAL = 'waiting_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::WAITING_APPROVAL => 'Menunggu Persetujuan',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::WAITING_APPROVAL => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-m-document-text',
            self::WAITING_APPROVAL => 'heroicon-m-clock',
            self::APPROVED => 'heroicon-m-check-circle',
            self::REJECTED => 'heroicon-m-x-circle',
            self::CANCELLED => 'heroicon-m-x-circle',
        };
    }
}
