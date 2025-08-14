<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\Api\SendPDF;
use App\Http\Controllers\Api\importBLController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        if (env('APP_ENV') === 'production') {
            $schedule->call(function () {
                // Instantiate and call the index function of the SendPDF controller
                $sendPDFController = new SendPDF();
                $sendPDFController->index();
            })->dailyAt('18:00'); // Adjust this as per your requirement (e.g., hourly(), weekly(), etc.)

            $schedule->call(function () {
                // Instantiate and call the index function of the SendPDF controller
                $sendPDFController = new SendPDF();
                $sendPDFController->index();
            })->dailyAt('23:50'); // Adjust this as per your requirement (e.g., hourly(), weekly(), etc.)

            $schedule->call(
                function () {
                    Artisan::call('app:check-no-baselink');
                }
            )->hourly();

            $schedule->call(
                function () {
                    try {
                        $importController = new importBLController();
                        $importController->runAll();
                    } catch (\Exception $e) {
                        Log::error('Scheduled importBLController failed: ' . $e->getMessage());
                    }
                }
            )->everyMinute();

            $schedule->call(function () {
                $results = DB::select("
                    SELECT
                        (SELECT NrDokumentu
                         FROM dbo.RuchMagazynowy r
                         LEFT JOIN dbo.ElementRuchuMagazynowego e ON e.IDRuchuMagazynowego = r.IDRuchuMagazynowego
                         WHERE e.IDElementuRuchuMagazynowego = z.IDElementuWZ) AS ndoc,
                        z.IDElementuWZ,
                        rm.NrDokumentu,
                        rm.\"Data\",
                        t.KodKreskowy,
                        erm.*
                    FROM dbo.ElementRuchuMagazynowego erm
                    LEFT JOIN dbo.RuchMagazynowy rm ON erm.IDRuchuMagazynowego = rm.IDRuchuMagazynowego
                    LEFT JOIN dbo.Towar t ON t.IDTowaru = erm.IDTowaru
                    JOIN dbo.ZaleznosciPZWZ z ON z.IDElementuPZ = erm.IDElementuRuchuMagazynowego
                    WHERE IDWarehouseLocation IN (
                        SELECT Zniszczony
                        FROM dbo.EMailMagazyn
                        WHERE Zniszczony IS NOT NULL
                    ) AND z.IDElementuWZ NOT IN (32378,230685,142812,173864)
                ");
                //
                if (!empty($results)) {
                    $emailData = [];
                    foreach ($results as $row) {
                        $emailData[] = [
                            'ndoc' => $row->ndoc,
                            'NrDokumentu' => $row->NrDokumentu,
                        ];
                    }

                    Mail::send([], [], function ($message) use ($emailData) {
                        $message->to('khanenko.igor@gmail.com')
                            ->subject('Błąd lokalizacji. Produkt został pobrany z niedozwolonej lokalizacji')
                            ->html('W następujących zamówieniach: ' . json_encode($emailData));
                    });
                }
            })->dailyAt('03:00');

            $schedule->exec('find /var/www/fulstor/storage/app/public/pdf -type f -delete')->dailyAt('04:00');
        }
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
