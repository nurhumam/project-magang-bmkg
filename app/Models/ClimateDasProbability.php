<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClimateDasProbability extends Model
{
    use HasFactory;
    protected $table = 'climate_das_probabilities';
    public $timestamps = false;
}