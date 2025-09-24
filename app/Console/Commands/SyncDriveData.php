<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class SyncDriveData extends Command
{
    protected $signature = 'app:sync-drive-data';
    protected $description = 'Sinkronisasi data iklim historis dari Google Drive';

    public function handle()
    {
        $this->info('Memulai skrip sinkronisasi data dari Google Drive...');

        // DIUBAH: Kita gunakan 'python' saja, karena path lengkap lebih rentan error di environment berbeda
        // Pastikan 'python' ada di PATH environment variable Anda.
        $process = Process::path(base_path())
            ->env([
                'DB_HOST' => config('database.connections.mysql.host'),
                'DB_DATABASE' => config('database.connections.mysql.database'),
                'DB_USERNAME' => config('database.connections.mysql.username'),
                'DB_PASSWORD' => config('database.connections.mysql.password'),
            ])
            ->timeout(900)
            ->run('python drive_importer.py');

        if ($process->successful()) {
            $this->info('Skrip Python berhasil dijalankan.');
            $this->line('Output: ' . $process->output()); // Tampilkan juga output sukses
            Log::info('SyncDriveData Success: ' . $process->output());
        } else {
            // INI BAGIAN PENTINGNYA
            $this->error('Skrip Python GAGAL dijalankan. Berikut adalah detail errornya:');
            $this->line($process->errorOutput()); // Tampilkan error spesifik dari Python
            Log::error('SyncDriveData Error: ' . $process->errorOutput());
        }
    }
}