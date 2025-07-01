<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = [
        'name',
        'type',
        'value',
        'min_amount',
        'max_amount',
        'valid_from',
        'valid_until',
        'is_active',
    ];
}
