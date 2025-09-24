<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClimateAnalysis extends Model
{
    use HasFactory;
    protected $table = 'climate_analyses';
    public $timestamps = false;
}