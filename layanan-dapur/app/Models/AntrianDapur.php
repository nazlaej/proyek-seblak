<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AntrianDapur extends Model
{
    protected $table = 'antrian_dapur';

    protected $fillable = [
        'order_id',
        'nama_pelanggan',
        'detail_item',
        'status',
    ];

    protected $casts = [
        'detail_item' => 'array',
    ];
}
