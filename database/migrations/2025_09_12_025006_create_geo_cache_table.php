<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('geo_cache', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 11, 7);
            $table->string('desa')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten')->nullable();
            $table->string('provinsi')->nullable();
            $table->text('display_name')->nullable(); // Untuk menyimpan nama lengkap
            $table->timestamps();

            // Index untuk mempercepat pencarian berdasarkan koordinat
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geo_cache');
    }
};
