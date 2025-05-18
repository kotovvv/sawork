<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class PrintController extends Controller
{
    private $usersPrinters = [
        '4' => ['invoice' => 'HP-LaserJet-Pro-Stan1', 'ttn' => 'HP-LaserJet-Pro-Stan1'],
        '3' => ['invoice' => 'HP-LaserJet-Pro-Stan1', 'ttn' => 'HP-LaserJet-Pro-Stan1'],
    ];


    public function print(Request $request)
    {
        $userId = $request->user->IDUzytkownika;

        if ($request->input('doc') == 'invoice') {
            $order = $request->order;
            $IDMagazynu = $order['IDWarehouse'];
            $symbol = DB::table('Magazyn')->where('IDMagazynu', $IDMagazynu)->value('Symbol');
            $fileName =  str_replace(['/', '\\'], '_', $order['invoice_number']);
            $fileName = "pdf/{$symbol}/{$fileName}.pdf";
            $path = storage_path('app/public/' . $fileName);
            $printer = $this->usersPrinters[$userId]['invoice'];
            // Проверка готовности принтера
            $printerStatus = [];
            exec("lpstat -p " . escapeshellarg($printer), $printerStatus);
            $isReady = false;
            foreach ($printerStatus as $line) {
                if (strpos($line, 'is idle') !== false || strpos($line, 'is ready') !== false) {
                    $isReady = true;
                    break;
                }
            }
            if (!$isReady) {
                return response()->json(['status' => 'error', 'message' => 'Printer not ready'], 400);
            }
            if (file_exists($path)) {
                exec("lpr -P " . escapeshellarg($printer) . " " . escapeshellarg($path));
            } else {
                $token = $this->getToken($IDMagazynu);
                \App\Jobs\DownloadInvoicePdf::dispatch($IDMagazynu, $orders['invoice_number'], $orders['invoice_id'], $token)
                    ->chain([
                        function () use ($printer, $path) {
                            if (file_exists($path)) {
                                exec("lpr -P " . escapeshellarg($printer) . " " . escapeshellarg($path));
                            }
                        }
                    ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function getToken($IDMagazynu)
    {
        return DB::table('settings')->where('obj_name', 'sklad_token')->where('key', $IDMagazynu)->value('value');
    }
}
