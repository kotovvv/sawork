<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ForTtn;
use App\Models\CourierForms;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ForTTNController extends Controller
{
    // List all rows
    public function index()
    {
        $rows = ForTtn::get();
        $forms = CourierForms::pluck('form', 'courier_code')->toArray();
        $warehouseIds = $rows->pluck('id_warehouse')->unique()->toArray();
        $symbols = DB::table('Magazyn')
            ->whereIn('IDMagazynu', $warehouseIds)
            ->pluck('Symbol', 'IDMagazynu');

        foreach ($rows as $row) {
            $row->symbol = $symbols[$row->id_warehouse] ?? null;
            $row->form = isset($forms[$row->courier_code]) ? json_decode($forms[$row->courier_code], true) : [];
        }
        return $rows;
    }

    // Store new row
    public function store(Request $request)
    {
        $data = $request->validate([
            'api_service_id' => 'required|integer',
            'id_warehouse' => 'required|integer',
            'delivery_method' => 'required|string|max:100',
            'order_source' => 'required|string|max:20',
            'order_source_id' => 'required|integer',
            'order_source_name' => 'required|string|max:150',
            'courier_code' => 'required|string|max:20',
            'account_id' => 'required|integer',
            'service' => 'nullable|string|max:50',
            'info_account' => 'nullable|json',
        ]);
        $id = ForTtn::insertGetId($data);
        return response()->json(['id' => $id], 201);
    }

    // Show a single row
    public function show($id)
    {
        $row = ForTtn::find($id);
        if (!$row) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return $row;
    }

    // Update a row
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'api_service_id' => 'required|integer',
            'id_warehouse' => 'required|integer',
            'delivery_method' => 'required|string|max:100',
            'order_source' => 'required|string|max:20',
            'order_source_id' => 'required|integer',
            'order_source_name' => 'required|string|max:150',
            'courier_code' => 'required|string|max:20',
            'account_id' => 'required|integer',
            'service' => 'nullable|string|max:50',
            'info_account' => 'nullable|json',
        ]);
        $updated = ForTtn::where('id', $id)->update($data);
        if (!$updated) {
            return response()->json(['error' => 'Not found or not updated'], 404);
        }
        return response()->json(['success' => true]);
    }

    // Delete a row
    public function destroy($id)
    {
        $deleted = ForTtn::where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(['success' => true]);
    }

    private function getToken($IDMagazynu)
    {
        return DB::table('settings')->where('obj_name', 'sklad_token')->where('key', $IDMagazynu)->value('value');
    }

    // Get codes from BaseLinker
    public function getCodesFromBL($id_warehouse)
    {
        // $cacheKey = 'codeBL';
        // $codeBL = cache()->get($cacheKey);
        if (ENV('APP_ENV') != 'production') {
            return [['code' => '0', 'name' => 'Not']];
        }
        // if (!$codeBL) {
        $token = $this->getToken($id_warehouse);
        if (!$token) {
            return response()->json(['error' => 'Token not found'], 404);
        }

        try {
            $BL = new \App\Http\Controllers\Api\BaseLinkerController($token);
            $response = $BL->getCouriersList([]);
            if (!$response['status'] == 'SUCCESS') {
                $messages[] = 'error getCodesFromBL: ';
                throw new \Exception('Error getCodesFromBL in BL');
            }
            if (!isset($response['couriers']) || empty($response['couriers'])) {
                return response()->json(['error' => 'No couriers found'], 404);
            }
        } catch (\Exception $e) {
            Log::error('BaseLinker initialization or getCouriersList failed: ' . $e->getMessage());
            return response()->json(['error' => 'BaseLinker error: ' . $e->getMessage()], 500);
        }

        $codeBL = $response['couriers'];
        //     cache()->put($cacheKey, $codeBL, now()->addDay());
        // }

        return $codeBL;
    }

    private function getCourierFields($courier_code, $BL)
    {

        $fields = $BL->getCourierFields([
            'courier_code' => $courier_code

        ]);
        if (isset($fields['status']) && $fields['status'] === 'SUCCESS') {
            $fields = $fields;
        } else {
            $fields = [];
        }
        if (!empty($fields)) {
            $jsonFields = json_encode($fields, JSON_UNESCAPED_UNICODE);
            CourierForms::updateOrCreate(
                ['courier_code' => $courier_code],
                ['form' => $jsonFields]
            );
        }
    }

    public function getAccountsFromBL($id_warehouse, $courier_code)
    {
        // $cacheKey = 'accountBL' . $courier_code;
        // $accountBL = cache()->get($cacheKey);

        // if (!$accountBL) {
        $token = $this->getToken($id_warehouse);
        if (!$token) {
            return response()->json(['error' => 'Token not found'], 404);
        }

        try {
            $BL = new \App\Http\Controllers\Api\BaseLinkerController($token);
            $response = $BL->getCourierAccounts(['courier_code' => $courier_code]);
            if (!$response['status'] == 'SUCCESS') {
                $messages[] = 'error getAccountsFromBL: ';
                throw new \Exception('Error getAccountsFromBL in BL');
            }
            if (!isset($response['accounts']) || empty($response['accounts'])) {
                return response()->json(['error' => 'No accounts found'], 404);
            }

            $accountBL = $response['accounts'];
            //     cache()->put($cacheKey, $accountBL, now()->addDay());
            // }
            $this->getCourierFields($courier_code, $BL);
            return $accountBL;
        } catch (\Exception $e) {
            Log::error('BaseLinker initialization or getCourierAccounts failed: ' . $e->getMessage());
            return response()->json(['error' => 'BaseLinker error: ' . $e->getMessage()], 500);
        }
    }

    private function getOrderInfo($IDOrder, $IDWarehouse)
    {
        $res = [];
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

        $res = [
            'courier_code' => $forttn->courier_code,
            'account_id' => $forttn->account_id,
        ];
        $order = DB::table('orders as ord')
            ->select(DB::raw('(SELECT CAST(SUM(ol.PriceGross * ol.Quantity) AS DECIMAL(10,2)) FROM OrderLines ol WHERE ol.IDOrder = ord.IDOrder) as KwotaBrutto'))
            ->where('IDOrder', $IDOrder)
            ->where('IDWarehouse', $IDWarehouse)
            ->first();

        $res['KwotaBrutto'] = $order->KwotaBrutto;
        return $res;
    }

    public function getForm(Request $request, $id)
    {
        $res = [];

        $values = DB::connection('second_mysql')->table('order_details as od')
            ->join('for_ttn as ft', function ($join) {
                $join->on('ft.id_warehouse', '=', 'od.IDWarehouse')
                    ->on('ft.order_source_id', '=', 'od.order_source_id')
                    ->on('ft.order_source', '=', 'od.order_source')
                    ->on('ft.delivery_method', '=', 'od.delivery_method');
                //->on('ft.order_source_name', '=', 'od.order_source_name')
                //->on('ft.courier_code', '=', 'od.courier_code')
                //->on('ft.account_id', '>', 0);
            })
            ->join('courier_forms as cf', 'cf.courier_code', '=', 'ft.courier_code')
            ->where('od.order_id', $id)
            ->where('ft.account_id', '>', 0)
            ->select('cf.form', 'cf.default_values', 'ft.service')->first();
        //->value(DB::raw('JSON_OBJECT("form", cf.form, "default_values", cf.default_values)'));
        if (!$values) {
            return response()->json(['error' => 'Form not found'], 404);
        }
        $res['fields'] = json_decode($values->form, true);
        $res['default_values'] = json_decode($values->default_values, true);
        $res['hidden'] =  $request->user->IDRoli == 1 ? 0 : 1;
        $service = $values->service;

        $order = DB::table('orders as ord')
            ->select('ord.Number', 'ord._OrdersTempDecimal2 as NumberBL', DB::raw('(SELECT CAST(SUM(ol.PriceGross * ol.Quantity) AS DECIMAL(10,2)) FROM OrderLines ol WHERE ol.IDOrder = ord.IDOrder) as KwotaBrutto'))
            ->where('IDOrder', $id)
            ->first();
        /*
"id": "insurance" = стоимость заказа
"id":"reference_number" = "BL "
"id": "package_description"= "ZO "
*/
        $res['default_values']['fields']['insurance'] = $order->KwotaBrutto;
        $res['default_values']['fields']['reference_number'] = (int)$order->NumberBL;
        $res['default_values']['fields']['package_description'] = str_replace(['/', '-'], [' ', ''], $order->Number);
        $res['default_values']['package_fields']['parcel_reference'] = (int)$order->NumberBL;
        if (!is_null($service)) {
            $res['default_values']['fields']['service'] = $service;
        }

        return $res;
    }

    public function getTTN(Request $request)
    {
        $data = $request->validate([
            'IDOrder' => 'required|integer',
            'IDWarehouse' => 'required|integer',
            'forttn.order_id' => 'required|integer',
            'Nr_Baselinker' => 'required|string|max:50',
            // add other rules for nested fields if needed
        ]);

        $token = $this->getToken($data['IDWarehouse']);
        if (!$token) {
            return response()->json(['error' => 'Token not found'], 404);
        }

        try {
            $BL = new \App\Http\Controllers\Api\BaseLinkerController($token);

            $parameters = [
                'order_id' => $data['Nr_Baselinker'],
                'get_unconfirmed_orders' => true,
                'include_custom_extra_fields' => true,
            ];
            if (!$BL->inKompletowanie($parameters)) {
                $messages[] = 'Order BL ' . $data['Nr_Baselinker'] . ' ne Kompletowanie';
                return response()->json(['error' => 'Order not in Kompletowanie status'], 404);
            }
        } catch (\Exception $e) {
            Log::error('BaseLinker initialization or inKompletowanie check failed: ' . $e->getMessage());
            return response()->json(['error' => 'BaseLinker error: ' . $e->getMessage()], 500);
        }

        $forttn = $request->input('forttn', []);

        $orderInfo = $this->getOrderInfo($data['IDOrder'], $data['IDWarehouse']);
        if (isset($orderInfo['error'])) {
            return ['error' => $orderInfo['error']];
        }

        $forttn['courier_code'] = $orderInfo['courier_code'];
        $forttn['account_id'] = $orderInfo['account_id'];
        if (isset($forttn['fields']) && is_array($forttn['fields'])) {
            $forttn['fields'] = array_values(array_filter($forttn['fields'], function ($field) {
                return !array_key_exists('value', $field) || $field['value'] !== null;
            }));
        }

        if (env('APP_ENV') == 'production') {
            $createdTTN = $BL->createPackage($forttn);
            $attempts = 0;
            $maxAttempts = 3;
            $valid = false;
            while ($attempts < $maxAttempts && !$valid) {
                if (isset($createdTTN['package_number']) && preg_match('/\d.*\d/', $createdTTN['package_number'])) {
                    $valid = true;
                } else {
                    $attempts++;
                    if ($attempts < $maxAttempts) {
                        usleep(2000000); // 2 сек задержка
                        $createdTTN = $BL->createPackage($forttn);
                    }
                }
            }

            if (!$valid) {
                return response()->json(['error' => 'Invalid package number'], 404);
            }

            if ($createdTTN['status'] == 'ERROR') {
                return response()->json(['error_message' => $createdTTN['error_code'] . ' ' . $createdTTN['error_message']], 404);
            }
            DB::table('Orders')->where('IDOrder', $data['IDOrder'])->where('IDWarehouse', $data['IDWarehouse'])
                ->update(['_OrdersTempString2' => $createdTTN['package_number']]);

            // Return three values: package_id, package_number, courier_inner_number
            return response()->json([
                'package_id' => $createdTTN['package_id'],
                'package_number' => $createdTTN['package_number'],
                'courier_inner_number' => $createdTTN['courier_inner_number'],
                'courier_code' => $forttn['courier_code'],
                // 'filePath' => $filePath,
                //'extension' => $label['extension'] ?? null,
                //'label' => $label['label'] ?? null,
            ]);
        } else {
            return response()->json([
                'package_id' => 123123123456,
                'package_number' => 123123456,
                'courier_inner_number' => 123123456,
                'filePath' => '',
                //'extension' => $label['extension'] ?? null,
                //'label' => $label['label'] ?? null,
            ]);
        }
    }
}
