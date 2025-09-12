<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeoCache extends Model
{
    use HasFactory;

    protected $table = 'geo_cache';

    protected $fillable = [
        'latitude',
        'longitude',
        'desa',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'display_name',
    ];
}