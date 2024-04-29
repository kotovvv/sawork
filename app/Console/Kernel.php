<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\Api\SendPDF;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function () {
            // Instantiate and call the index function of the SendPDF controller
            $sendPDFController = new SendPDF();
            $sendPDFController->index();
        })->dailyAt('17:00'); // Adjust this as per your requirement (e.g., hourly(), weekly(), etc.)
        $schedule->call(function () {
            // Instantiate and call the index function of the SendPDF controller
            $sendPDFController = new SendPDF();
            $sendPDFController->index();
        })->dailyAt('23:50'); // Adjust this as per your requirement (e.g., hourly(), weekly(), etc.)
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
