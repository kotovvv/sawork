<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class PrintController extends Controller
{

    private $usersPrinters = [
        '4' => ['invoice' => 'HP-LaserJet-Pro-Stan2', 'ttn' => 'HP-LaserJet-Pro-Stan2'],
        '3' => ['invoice' => 'HP-LaserJet-Pro-Stan1', 'ttn' => 'HP-LaserJet-Pro-Stan1'],
        '1012' => ['invoice' => 'HP-LaserJet-Pro-Stan3', 'ttn' => 'HP-LaserJet-Pro-Stan3'],
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
                \Log::error("Printer {$printer} is not ready for user {$userId}");
                return response()->json(['status' => 'error', 'message' => 'Printer not ready'], 400);
            }
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
        if ($request->input('doc') == 'label') {
            $printer = $this->usersPrinters[$userId]['ttn'];
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
                \Log::error("Printer {$printer} is not ready for user {$userId}");
                return response()->json(['status' => 'error', 'message' => 'Printer not ready'], 400);
            }
            if (file_exists($request->path)) {
                exec("lpr -P " . escapeshellarg($printer) . " " . escapeshellarg($request->path));
            } else {
                \Log::error("File not found: " . $request->path);
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
