<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SyncDasarianData extends Command
{
    
    protected $signature = 'app:sync-dasarian';
    protected $description = 'Menjalankan skrip Python untuk menyinkronkan hanya data Prediksi dan Peluang Dasarian.';

    public function handle()
    {
        $this->info('Memulai skrip sinkronisasi data Dasarian...');

        $process = Process::path(base_path())
            ->timeout(1500)
            ->run('python master_importer.py dasarian');

        if ($process->successful()) {
            $this->info('Skrip Python Dasarian berhasil dijalankan.');
            $this->line($process->output());
        } else {
            $this->error('Skrip Python Dasarian GAGAL dijalankan.');
            $this->line($process->errorOutput());
        }
    }
}