<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Expense extends Model implements HasMedia
{
    use InteractsWithMedia;
    protected $fillable = [
        'category',
        'description',
        'amount',
        'expense_date',
        'receipt_number',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipt')
            ->singleFile();
    }
}
