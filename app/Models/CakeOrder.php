<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CakeOrder extends Model
{
    protected $fillable = ['spec', 'subtotal', 'gst', 'total'];

    protected $casts = [
        'spec' => 'array',
    ];
}
