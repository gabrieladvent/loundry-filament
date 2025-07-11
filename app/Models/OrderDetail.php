<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    protected $fillable = [
        'order_id',
        'service_id',
        'quantity',
        'weight',
        'unit_price',
        'price',
        'notes',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function getWeightAttribute()
    {
        return $this->quantity;
    }

    // Mutator untuk weight
    public function setWeightAttribute($value)
    {
        $this->attributes['quantity'] = $value;
    }
}
