<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'order_id',
        'item_category_id',
        'name',
        'description',
        'quantity',
        'before',
        'condition_after',
        'notes'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function itemCategory()
    {
        return $this->belongsTo(ItemCategory::class);
    }
}
