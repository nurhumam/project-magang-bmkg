<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Biasanya command akan terdeteksi otomatis,
        // tapi jika tidak, Anda bisa mendaftarkannya di sini.
        // Contoh: \App\Console\Commands\SyncDriveData::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        dd('File Kernel.php dan method schedule() ini TERBACA.'); // <-- TAMBAHKAN INI

        // Kode Anda yang lama biarkan saja di bawahnya
        $schedule->command('app:sync-drive-data')->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}