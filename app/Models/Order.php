<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_code',
        'customer_id',
        'user_id',
        'order_date',
        'pickup_date',
        'delivery_date',
        'estimated_finish',
        'actual_finish',
        'status',
        'payment_status',
        'pickup_type',
        'delivery_type',
        'pickup_address',
        'delivery_address',
        'total_weight',
        'total_items',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'additional_fee',
        'total_amount',
        'paid_amount',
        'change_amount',
        'payment_id',
        'notes',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function transactionDiscounts()
    {
        return $this->hasMany(TransactionDiscount::class);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Scope untuk filter berdasarkan payment status
    public function scopePaymentStatus($query, $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    // Accessor untuk mendapatkan total yang sudah dibayar
    public function getRemainingAmountAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }
}
