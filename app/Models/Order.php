<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id',
        'machine_id',
        'service_id',
        'item_id',
        'discount_id',
        'payment_id',
        'total',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }


    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
