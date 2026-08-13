<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'total_price', 'subtotal',
        'tax', 'shipping_cost', 'discount', 'status',
        'payment_status', 'payment_method', 'shipping_address',
        'shipping_city', 'shipping_country', 'shipping_phone',
        'notes', 'shipped_at', 'delivered_at'
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}