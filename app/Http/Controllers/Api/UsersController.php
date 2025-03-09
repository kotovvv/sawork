<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class UsersController extends Controller
{
    public function index()
    {
        return response()->json(DB::table('users')->select('users.*', 'NazwaUzytkownika')->leftJoin('Uzytkownik', 'IDUzytkownika', '=', 'ID')->get());
    }

    public function store(Request $request)
    {
        DB::table('users')->insert($request->all());
        $o_user = DB::table('users')->select('users.*', 'NazwaUzytkownika')->leftJoin('Uzytkownik', 'IDUzytkownika', '=', 'ID')->where('ID', $request->ID)->get();
        return $o_user;
    }

    public function show($id)
    {
        $o_user = DB::table('users')->find($id);
        if (is_null($o_user)) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($o_user);
    }

    public function update(Request $request, $id)
    {
        $o_user = DB::table('users')->find($id);
        if (is_null($o_user)) {
            return response()->json(['message' => 'User not found'], 404);
        }

        DB::table('users')->where('id', $id)->update($request->except('NazwaUzytkownika'));
        $o_user = DB::table('users')->select('users.*', 'NazwaUzytkownika')->leftJoin('Uzytkownik', 'IDUzytkownika', '=', 'ID')->find($id);
        return response()->json($o_user);
    }

    public function destroy($id)
    {
        $o_user = DB::table('users')->find($id);
        if (is_null($o_user)) {
            return response()->json(['message' => 'User not found'], 404);
        }
        DB::table('users')->where('id', $id)->delete();
        return response()->json(null, 204);
    }

    public function uzytkownicy()
    {
        $o_user = DB::table('Uzytkownik')
            ->select('IDUzytkownika', 'NazwaUzytkownika as title')
            ->where('Aktywny', 1)
            ->where('IDRoli', '<', 4)
            ->whereNotIn('IDUzytkownika', function ($query) {
                $query->select('ID')->from('users');
            })
            ->get();

        if ($o_user->isEmpty()) {
            return response()->json(['message' => 'No users found'], 404);
        }
        return response()->json($o_user);
    }
}
