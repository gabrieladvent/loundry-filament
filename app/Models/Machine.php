<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    protected $fillable = [
        'name',
        'type',
        'capacity_kg',
        'status',
        'last_maintenance',
        'notes',
        'is_active',
    ];
}
