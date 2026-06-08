<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'order_id',
        'order_number',
        'customer_name',
        'email',
        'phone',
        'address',
        'payment_method',
        'total_amount',
        'notes',
        'delivery_date',
        'preferred_time',
        'status',
        'transaction_id'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'delivery_date' => 'date',
        'order_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'phone', 'phone');
    }

    public function logs()
    {
        return $this->hasMany(OrderLog::class);
    }
}
