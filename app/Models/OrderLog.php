<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderLog extends Model
{
    protected $table = 'order_logs';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'status',
        'notes'
    ];

    protected $casts = [
        'order_id' => 'integer',
        'created_at' => 'datetime'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
