<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationGrid extends Model
{
    use HasFactory;
    protected $table = 'location_grids';
    public $timestamps = false;
}