<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class UsersController extends Controller
{
    public function index()
    {
        $response = [];
        $response['settings'] = DB::table('settings')
            ->where('obj_name', 'sklad_token')
            ->orWhere('obj_name', 'ext_id')
            ->get();
        $response['users'] = DB::table('Uzytkownik')
            ->select('IDUzytkownika', 'NazwaUzytkownika as title')
            ->where('Aktywny', 1)
            ->where('IDRoli', '<', 4)
            ->get();
        $response['warehouses'] = DB::table('Magazyn')
            ->select('IDMagazynu', 'Nazwa as title')
            ->get();

        return response($response);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        if ($data['obj_name'] === 'sklad_token' && isset($data['value'])) {
            $data['value'] = Crypt::encryptString($data['value']);
            $existingSetting = DB::table('settings')
                ->where('obj_name', 'sklad_token')
                ->where('key', $data['key'])
                ->first();

            if ($existingSetting) {
                DB::table('settings')
                    ->where('id', $existingSetting->id)
                    ->update(['value' => $data['value']]);
            } else {
                DB::table('settings')->insert($data);
            }
        } else {
            $existingSetting = DB::table('settings')
                ->where('obj_name', 'ext_id')
                ->where('for_obj', $data['for_obj'])
                ->where('key', $data['key'])
                ->first();
            if ($existingSetting) {
                DB::table('settings')
                    ->where('id', $existingSetting->id)
                    ->update(['value' => $data['value']]);
            } else {
                DB::table('settings')->insert($data);
            }
        }

        $o_setting = DB::table('settings')
            ->where('obj_name', $data['obj_name'])
            ->when(isset($data['for_obj']), function ($query) use ($data) {
                return $query->where('for_obj', $data['for_obj']);
            })
            ->where('key', $data['key'])
            ->first();
        return response()->json($o_setting);
    }

    public function show($id)
    {
        $o_setting = DB::table('settings')->find($id);
        if (is_null($o_setting)) {
            return response()->json(['message' => 'Setting not found'], 404);
        }
        return response()->json($o_setting);
    }

    public function update(Request $request, $id)
    {
        $o_setting = DB::table('settings')->find($id);
        if (is_null($o_setting)) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        $data = $request->all();
        if ($data['obj_name'] === 'sklad_token' && isset($data['value'])) {
            $data['value'] = Crypt::encryptString($data['value']);
        }
        DB::table('settings')->where('id', $id)->update($data);
        $o_setting = DB::table('settings')->find($id);
        return response()->json($o_setting);
    }

    public function destroy($id)
    {
        $o_setting = DB::table('settings')->find($id);
        if (is_null($o_setting)) {
            return response()->json(['message' => 'Setting not found'], 404);
        }
        DB::table('settings')->where('id', $id)->delete();
        return response()->json(null, 204);
    }

    public function uzytkownicy()
    {
        $o_user = DB::table('Uzytkownik')
            ->select('IDUzytkownika', 'NazwaUzytkownika as title')
            ->where('Aktywny', 1)
            ->where('IDRoli', '<', 4)
            ->whereNotIn('IDUzytkownika', function ($query) {
                $query->select('key')->from('settings')->where('obj_name', 'ext_id');
            })
            ->get();

        if ($o_user->isEmpty()) {
            return response()->json(['message' => 'No users found'], 404);
        }
        return response()->json($o_user);
    }
}
