<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartSession extends Model
{
    protected $table = 'cart_sessions';

    protected $fillable = [
        'session_id',
        'medicine_id',
        'quantity',
        'price'
    ];

    protected $casts = [
        'medicine_id' => 'integer',
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
