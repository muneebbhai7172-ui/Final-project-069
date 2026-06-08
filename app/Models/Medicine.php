<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $table = 'medicines';

    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'reserved_quantity',
        'expiry_date',
        'manufacturer',
        'batch_number',
        'category',
        'image'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'expiry_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartSessions()
    {
        return $this->hasMany(CartSession::class);
    }

    public function scopeLowStock($query, $threshold = 20)
    {
        return $query->where('quantity', '<', $threshold)->where('quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->whereDate('expiry_date', '<=', now()->addDays($days))
                    ->whereDate('expiry_date', '>=', now());
    }
}
