<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'payment_name',
        'notes',
        'is_active',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
