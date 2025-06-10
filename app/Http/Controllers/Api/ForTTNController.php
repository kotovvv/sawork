<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ForTtn;
use Illuminate\Support\Facades\DB;

class ForTTNController extends Controller
{
    // List all rows
    public function index()
    {
        return ForTtn::get();
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
        $cacheKey = 'codeBL';
        $codeBL = cache()->get($cacheKey);

        if (!$codeBL) {
            $token = $this->getToken($id_warehouse);
            if (!$token) {
                return response()->json(['error' => 'Token not found'], 404);
            }

            $BL = new \App\Http\Controllers\Api\BaseLinkerController($token);
            $response = $BL->getCouriersList([]);
            if (!$response['status'] == 'SUCCESS') {
                $messages[] = 'error getCodesFromBL: ';
                throw new \Exception('Error getCodesFromBL in BL');
            }
            if (!isset($response['couriers']) || empty($response['couriers'])) {
                return response()->json(['error' => 'No couriers found'], 404);
            }

            $codeBL = $response['couriers'];
            cache()->put($cacheKey, $codeBL, now()->addDay());
        }

        return $codeBL;
    }
    public function getAccountsFromBL($id_warehouse, $courier_code)
    {
        $cacheKey = 'accountBL' . $courier_code;
        $accountBL = cache()->get($cacheKey);

        if (!$accountBL) {
            $token = $this->getToken($id_warehouse);
            if (!$token) {
                return response()->json(['error' => 'Token not found'], 404);
            }

            $BL = new \App\Http\Controllers\Api\BaseLinkerController($token);
            $response = $BL->getCouriersList([]);
            if (!$response['status'] == 'SUCCESS') {
                $messages[] = 'error getAccountsFromBL: ';
                throw new \Exception('Error getAccountsFromBL in BL');
            }
            if (!isset($response['accounts']) || empty($response['accounts'])) {
                return response()->json(['error' => 'No accounts found'], 404);
            }

            $accountBL = $response['accounts'];
            cache()->put($cacheKey, $accountBL, now()->addDay());
        }

        return $accountBL;
    }
}
