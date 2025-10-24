<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSpatialIndexToClimateTables extends Migration
{
    public function up()
    {
        $tables = ['climate_normals', 'climate_analyses', 'climate_predictions'];

        foreach ($tables as $table) {

            // --- PERBAIKAN 1 ---
            // Ubah dari NOT NULL menjadi NULL agar lebih fleksibel
            DB::statement("ALTER TABLE `{$table}` ADD `location` POINT NULL COMMENT 'Spatial column (lon lat)'");

            // --- PERBAIKAN 2 ---
            // Hanya update baris yang lat/lon-nya tidak null
            DB::table($table)
                ->whereNotNull('longitude') // Tambahkan ini
                ->whereNotNull('latitude')  // Tambahkan ini
                ->update([
                    'location' => DB::raw('ST_PointFromText(CONCAT("POINT(", longitude, " ", latitude, ")"))')
                ]);

            Schema::table($table, function (Blueprint $table) {
                $table->spatialIndex('location');
            });
        }
    }

    // ... (fungsi down() Anda tetap sama) ...
    public function down()
    {
        // ...
    }
}