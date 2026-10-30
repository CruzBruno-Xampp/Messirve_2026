<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // La conciliación requiere que el programador de Laravel esté ejecutándose.
        // Primera fase: solo libera órdenes vencidas o canceladas verificadas.
        $schedule->command('messirve:conciliar-qr --limite=25')
            ->everyMinute()
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
