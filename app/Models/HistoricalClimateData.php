<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoricalClimateData extends Model
{
    use HasFactory;
    
    protected $table = 'historical_climate_data';
    public $timestamps = false;
    
    protected $fillable = [
        'latitude',
        'longitude',
        'ch',
        'sh_percent',
        'sh_percentil',
        'data_period',
    ];
}