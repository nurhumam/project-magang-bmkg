<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClimateData extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model ini.
     *
     * @var string
     */
    protected $table = 'id_grid_chprovkab_jawa'; // DIUBAH: Menunjuk ke tabel baru

    /**
     * Laravel secara default mengasumsikan ada kolom created_at dan updated_at.
     * Atur menjadi false jika tabel Anda tidak memilikinya.
     *
     * @var bool
     */
    public $timestamps = false;
}