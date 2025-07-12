<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrintController extends Controller
{

    private $usersPrinters = [
        '4' => ['invoice' => 'HP-LaserJet-Pro-Stan2', 'ttn' => 'XP-Stan2'],
        '3' => ['invoice' => 'HP-LaserJet-Pro-Stan1', 'ttn' => 'XP-Stan1'],
        '1012' => ['invoice' => 'HP-LaserJet-Pro-Stan3', 'ttn' => 'XP-Stan3'],
        '1013' => ['invoice' => 'HP-LaserJet-Pro-Stan4', 'ttn' => 'XP-Stan4'],
    ];

    public function print(Request $request)
    {
        $userId = $request->user->IDUzytkownika;

        if ($request->input('doc') == 'invoice') {
            $order = $request->order;
            $OrdersTempString7 = DB::table('Orders')->where('IDOrder', $order['IDOrder'])->value('_OrdersTempString7');
            $notprint = in_array($OrdersTempString7, ['personal_Product replacement', 'personal_Blogger', 'personal_Reklamacja, ponowna wysyłka']);
            if ($notprint) {
                Log::info("Order {$order['IDOrder']} not printed due to condition {$OrdersTempString7}");
                return response()->json(['status' => 'ok', 'message' => $OrdersTempString7 . ' nie ma faktury', 'nofaktura' => $OrdersTempString7 . ' nie ma faktury'], 200);
            }

            $IDMagazynu = $order['IDWarehouse'];
            $symbol = DB::table('Magazyn')->where('IDMagazynu', $IDMagazynu)->value('Symbol');
            $fileName =  str_replace(['/', '\\'], '_', $order['invoice_number']);
            $fileName = "pdf/{$symbol}/{$fileName}.pdf";
            $path = storage_path('app/public/' . $fileName);
            $printer = $this->usersPrinters[$userId]['invoice'];

            if (file_exists($path)) {
                exec("lpr -P " . escapeshellarg($printer) . " " . escapeshellarg($path));
            } else {
                $token = $this->getToken($IDMagazynu);
                \App\Jobs\DownloadInvoicePdf::dispatch($IDMagazynu, $order['invoice_number'], $order['invoice_id'], $token)
                    ->chain([
                        function () use ($printer, $path) {
                            if (file_exists($path)) {
                                exec("lpr -P " . escapeshellarg($printer) . " " . escapeshellarg($path));
                            }
                        }
                    ]);
            }
        }
        if ($request->input('doc') == 'ttn') {
            $printer = $this->usersPrinters[$userId]['ttn'];
            // $printerStatus = [];
            // exec("lpstat -p " . escapeshellarg($printer), $printerStatus);
            // $isReady = false;
            // foreach ($printerStatus as $line) {
            //     if (strpos($line, 'is idle') !== false || strpos($line, 'is ready') !== false) {
            //         $isReady = true;
            //         break;
            //     }
            // }
            // if (!$isReady) {
            //     Log::error("Printer {$printer} is not ready for user {$userId}");
            //     return response()->json(['status' => 'error', 'message' => 'Printer not ready'], 400);
            // }
            if (file_exists($request->path)) {
                exec("lpr -P " . escapeshellarg($printer) . " " . escapeshellarg($request->path));
            } else {
                Log::error("File not found: " . $request->path);
                return response()->json(['status' => 'error', 'message' => 'File not found'], 404);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function getToken($IDMagazynu)
    {
        return DB::table('settings')->where('obj_name', 'sklad_token')->where('key', $IDMagazynu)->value('value');
    }
}
