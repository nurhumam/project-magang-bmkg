<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SyncAllData extends Command
{
    protected $signature = 'app:sync-all';
    protected $description = 'Menjalankan skrip Python master untuk menyinkronkan semua data iklim dari file lokal.';

    public function handle()
    {
        $this->info('Memulai skrip sinkronisasi data master...');

        $process = Process::path(base_path())
            ->timeout(600) // Beri waktu 10 menit untuk jaga-jaga
            ->run('python master_importer.py');

        if ($process->successful()) {
            $this->info('Skrip Python berhasil dijalankan.');
            $this->line($process->output());
        } else {
            $this->error('Skrip Python GAGAL dijalankan.');
            $this->line($process->errorOutput());
        }
    }
}