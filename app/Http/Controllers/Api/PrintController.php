<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Collect;
use App\Models\ForTtn;

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
            $notprint = in_array($OrdersTempString7, ['personal_Product replacement', 'personal_Blogger', 'personal_Reklamacja, ponowna wysyłka.']) || $order['IDWarehouse'] == 22;
            if ($notprint) {
                Log::info("Order {$order['IDOrder']} not printed due to condition {$OrdersTempString7}");
                return response()->json(['status' => 'ok', 'message' => 'NIE MA FAKTURY ' . $OrdersTempString7, 'nofaktura' => 'NIE MA FAKTURY ' . $OrdersTempString7], 200);
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


            // Initialize BaseLinker controller
            $labelData = null;
            $IDWarehouse = null;

            if ($request->input('IDWarehouse') !== null) {
                $labelData = [
                    'courier_code' => $request->input('courier_code'),
                    'package_id' => $request->input('package_id'),
                    'package_number' => $request->input('package_number')
                ];
                $IDWarehouse = $request->input('IDWarehouse');
            } elseif ($request->input('IDOrder') !== null) {
                $IDOrder = $request->input('IDOrder');
                $IDWarehouse = DB::table('Orders')
                    ->where('IDOrder', $IDOrder)
                    ->value('IDWarehouse');
                $delivery = DB::connection('second_mysql')->table('order_details')
                    ->select('order_source', 'order_source_id', 'delivery_method')
                    ->where('order_id', $IDOrder)
                    ->where('IDWarehouse', $IDWarehouse)
                    ->first();

                if (!$delivery) {
                    return ['error' => 'Delivery information not found'];
                }

                $forttn = ForTtn::where('id_warehouse', $IDWarehouse)
                    ->where('order_source', $delivery->order_source)
                    ->where('order_source_id', $delivery->order_source_id)
                    ->where('delivery_method',  $delivery->delivery_method)
                    ->where('courier_code', '!=', '')
                    ->where('account_id', '>', 0)
                    ->select('courier_code', 'account_id')
                    ->first();

                if (!$forttn) {
                    return ['error' => 'Courier/account not found'];
                }

                $courier_code = $forttn->courier_code;

                $json_ttn = Collect::where('IDOrder', $request->input('IDOrder'))->value('ttn');
                if (!$json_ttn) {
                    return response()->json(['error' => 'No label data found'], 404);
                }

                if (is_string($json_ttn)) {
                    $json_ttn = json_decode($json_ttn, true);
                }

                $package_number = $request->input('package_number');
                if (!$package_number || !isset($json_ttn[$package_number])) {
                    return response()->json(['error' => 'Package number not found in label data'], 404);
                }

                $package_data = $json_ttn[$package_number];
                $json_ttn = [
                    'courier_code' => $courier_code,
                    'package_id' => $package_data['package_id'] ?? null,
                    'package_number' => $package_number
                ];
                if (!isset($json_ttn['courier_code'], $json_ttn['package_id'], $json_ttn['package_number'])) {
                    return response()->json(['error' => 'Invalid label data'], 400);
                }

                $labelData = [
                    'courier_code' => $json_ttn['courier_code'],
                    'package_id' => $json_ttn['package_id'],
                    'package_number' => $json_ttn['package_number']
                ];
            }

            if ($labelData && $IDWarehouse) {
                if (env('APP_ENV') == 'production') {
                    $token = $this->getToken($IDWarehouse);
                    if (!$token) {
                        return response()->json(['error' => 'Token not found'], 404);
                    }

                    $BL = new \App\Http\Controllers\Api\BaseLinkerController($token);
                    $label = $BL->getLabel($labelData);

                    if ($label['status'] == 'ERROR') {
                        return response()->json(['error_message' => $label['error_code'] . ' ' . $label['error_message']], 404);
                    }

                    $labelContent = base64_decode($label['label']);
                    $extension = $label['extension'] ?? 'pdf';
                    $tmpFile = tempnam(sys_get_temp_dir(), 'label_') . '.' . $extension;
                    file_put_contents($tmpFile, $labelContent);

                    exec("lpr -P " . escapeshellarg($printer) . " " . escapeshellarg($tmpFile));
                    unlink($tmpFile);
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    private function getToken($IDMagazynu)
    {
        return DB::table('settings')->where('obj_name', 'sklad_token')->where('key', $IDMagazynu)->value('value');
    }
}
