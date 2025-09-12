<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClimateNormal extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model ini.
     *
     * @var string
     */
    protected $table = 'ch_normal'; // Sesuaikan dengan nama tabel Anda

    /**
     * Nonaktifkan timestamps (created_at & updated_at).
     *
     * @var bool
     */
    public $timestamps = false;
}