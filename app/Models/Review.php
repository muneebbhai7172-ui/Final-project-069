<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'reviews';

    public $timestamps = false;

    protected $fillable = [
        'medicine_id',
        'customer_name',
        'rating',
        'comment'
    ];

    protected $casts = [
        'medicine_id' => 'integer',
        'rating' => 'integer',
        'created_at' => 'datetime'
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
