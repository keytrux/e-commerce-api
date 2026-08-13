<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingAddress extends Model
{
    protected $fillable = [
        'user_id', 'address', 'city', 'country', 'state',
        'postal_code', 'phone', 'receiver_name', 'is_default'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}