<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClimatePrediction extends Model
{
    use HasFactory;
    protected $table = 'climate_predictions';
    public $timestamps = false;
}