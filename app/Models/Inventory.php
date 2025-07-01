<?php

namespace App\Models;

use App\Enum\InventoryStatusStock;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = [
        'name',
        'category',
        'unit',
        'current_stock',
        'minimum_stock',
        'unit_price',
        'supplier',
        'notes',
        'is_active',
    ];

    public function getStockStatusAttribute(): string
    {
        if ($this->current_stock == 0) {
            return InventoryStatusStock::OUT_OF_STOCK->value;
        }

        if ($this->current_stock < $this->minimum_stock) {
            return InventoryStatusStock::LOW_STOCK->value;
        }

        return InventoryStatusStock::NORMAL_STOCK->value;
    }
}
