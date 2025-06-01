<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckNoBaselink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-no-baselink';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'If product has no baselink, then product set in table Towar
_TowarTempBool1 = 1';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        DB::table('dbo.Towar')
            ->where('_TowarTempBool1', 1)
            ->update(['_TowarTempBool1' => NULL]);

        $oneHourAgo = Carbon::now()->subHour();

        $latestRecords = DB::table('lomag_baselinker_sync_app_dlq')
            ->select('client', 'message', DB::raw('ROW_NUMBER() OVER (PARTITION BY client ORDER BY created_date DESC) AS rn'))
            ->where('status', 'BUSINESS_ERROR')
            ->where('message', 'like', 'Products not found in Baselinker%')
            ->where('created_date', '>=', $oneHourAgo);


        $results = DB::table(DB::raw("({$latestRecords->toSql()}) as LatestRecords"))
            ->mergeBindings($latestRecords)
            ->where('rn', 1)
            ->get();


        $productsCod = [];
        foreach ($results as $key => $s_produsts) {
            $message = str_replace('Products not found in Baselinker: ', '', $s_produsts->message);
            $a_productsCod = explode(',', $message);
            foreach ($a_productsCod as $key => $s_productsCod) {
                $productsCod[] = $s_productsCod;
                DB::table('Towar')
                    ->where('KodKreskowy', $s_productsCod)
                    ->update(['_TowarTempBool1' => 1]);
            }
        }

        print_r($productsCod);
    }
}
