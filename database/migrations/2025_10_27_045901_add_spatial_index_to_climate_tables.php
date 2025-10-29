<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSpatialIndexToClimateTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // KOSONGKAN - Jangan buat kolom location atau index di sini
        // Script Python akan menanganinya
        echo "Migration skipped: Column 'location' and spatial index will be managed by Python script.\n";
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // KOSONGKAN atau tambahkan perintah DROP jika perlu rollback manual
        // Script Python TIDAK akan otomatis menghapus kolom saat rollback
        echo "Migration skipped: Column 'location' and spatial index are managed by Python script.\n";
        // Jika Anda ingin rollback bisa menghapus kolom:
        // $tables = ['climate_normals', 'climate_analyses', 'climate_predictions'];
        // foreach ($tables as $tableName) {
        //     DB::statement("ALTER TABLE `{$tableName}` DROP COLUMN IF EXISTS `location`");
        // }
    }
}