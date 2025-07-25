<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Collect;

class PrintController extends Controller
{

    private $usersPrinters = [
        '4' => ['invoice' => 'HP-LaserJet-Pro-Stan2', 'label' => 'XP-Stan2'],
        '3' => ['invoice' => 'HP-LaserJet-Pro-Stan1', 'label' => 'XP-Stan1'],
        '1012' => ['invoice' => 'HP-LaserJet-Pro-Stan3', 'label' => 'XP-Stan3'],
        '1013' => ['invoice' => 'HP-LaserJet-Pro-Stan4', 'label' => 'XP-Stan4'],
    ];

    public function print(Request $request)
    {

        $userId = $request->user->IDUzytkownika;
        if ($request->input('doc') == 'sendOrderToAdmin') {
            $IDOrder = $request->order['IDOrder'];
            $OrderNumber = $request->order['OrderNumber'];
            $Nr_Baselinker = $request->order['Nr_Baselinker'];
            $message = $request->message;
            $user = DB::table('Uzytkownik')->where('IDUzytkownika', $userId)->value('Login');
            $date = date('Y-m-d H:i:s');
            $addmessage = "ERROR Order: !{$message}! - {$user} - {$date}";
            Collect::where('IDUzytkownika', $userId)
                ->where('IDOrder', $IDOrder)
                ->update(['IDUzytkownika' => 4, 'Uwagi' => DB::raw("CONCAT(IFNULL(Uwagi, ''), ' {$addmessage}')")]);
            Log::info("OrderID: {$IDOrder}, OrderNumber: {$OrderNumber}, Message: {$message}, User: {$user}, Date: {$date}");
            $printText = "\n\nNr_Baselinker: {$Nr_Baselinker}\nOrderNumber: {$OrderNumber}\nMessage: {$message}\nUser: {$user}\nDate: {$date}\n";

            $printer = $this->usersPrinters[$userId]['label'];
            $tmpFile = tempnam(sys_get_temp_dir(), 'print_');
            file_put_contents($tmpFile, $printText);
            exec("lpr -P " . escapeshellarg($printer) . " " . escapeshellarg($tmpFile));
            unlink($tmpFile);
        }
        if ($request->input('doc') == 'invoice') {
            $order = $request->order;

            $OrdersTempString7 = DB::table('Orders')->where('IDOrder', $order['IDOrder'])->value('_OrdersTempString7');
            $notprint = in_array($OrdersTempString7, ['personal_Product replacement', 'personal_Blogger', 'personal_Reklamacja, ponowna wysyłka.']);
            if ($notprint) {
                Log::info("Order {$order['IDOrder']} not printed due to condition {$OrdersTempString7}");
                return response()->json(['status' => 'ok', 'message' => $OrdersTempString7 . ' nie ma faktury', 'nofaktura' => $OrdersTempString7 . ' nie ma faktury'], 200);
            }
            if (empty($order['invoice_number'])) {
                Log::info("Order {$order['IDOrder']} has no invoice number.");
                return response()->json(['status' => 'ok', 'message' => 'Brak numeru faktury'], 200);
            }

            $IDMagazynu = $order['IDWarehouse'];
            $symbol = str_replace(' ', '_', DB::table('Magazyn')->where('IDMagazynu', $IDMagazynu)->value('Symbol'));
            $fileName =  str_replace(['/', '\\'], '_', $order['invoice_number']);
            $fileName = "pdf/{$symbol}/{$fileName}.pdf";
            $path = storage_path('app/public/' . $fileName);
            $printer = $this->usersPrinters[$userId]['invoice'];
            if (file_exists($path)) {
                exec("lpr -P " . $printer . " " . $path);
            } else {
                $token = $this->getToken($IDMagazynu);
                \App\Jobs\DownloadInvoicePdf::dispatch($IDMagazynu, $order['invoice_number'], $order['invoice_id'], $token)
                    ->chain([
                        function () use ($printer, $path) {
                            if (file_exists($path)) {
                                exec("lpr -P " . $printer . " " . $path);
                            }
                        }
                    ]);
            }
        }
        if ($request->input('doc') == 'label') {
            $printer = $this->usersPrinters[$userId]['label'];

            if (file_exists($request->path)) {
                exec("lpr -P " . $printer . " " . $request->path);
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
