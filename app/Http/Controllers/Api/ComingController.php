<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComingController extends Controller
{
    public function getDM(Request $request)
    {
        $data = $request->all();
        $IDMagazyn = $data['IDMagazynu'];
        return DB::table('dbo.RuchMagazynowy')->select('IDRuchuMagazynowego', 'Data', 'NrDokumentu', 'WartoscDokumentu')->where('IDRodzajuRuchuMagazynowego', 200)->where('IDMagazynu', $IDMagazyn)->orderBy('Data', 'DESC')->get();
    }
}
