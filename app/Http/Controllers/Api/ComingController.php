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
        $IDMagazynu = $data['IDMagazynu'];
        return DB::table('dbo.RuchMagazynowy')->select('IDRuchuMagazynowego', 'Data', 'NrDokumentu', 'WartoscDokumentu')->where('IDRodzajuRuchuMagazynowego', 200)->where('IDMagazynu', $IDMagazynu)->orderBy('Data', 'DESC')->get();
    }

    public function createPZfromDM1(Request $request)
    {
        $data = $request->all();
        $IDMagazynu = $data['IDMagazynu'];
        $IDRuchuMagazynowego = $data['IDRuchuMagazynowego'];

        // create PZ
        $createPZ = [];
        $createPZ['IDMagazynu'] = $IDMagazynu;
        $createPZ['Data'] = date('Y/m/d H:i:s');
        $createPZ['IDRodzajuRuchuMagazynowego'] = 1;
        $createPZ['IDUzytkownika'] = 1;
        $createPZ['Operator'] = 1;
        $createPZ['IDCompany'] = 1;
        $createPZ['IDKontrahenta'] = '';
        $createPZ['Uwagi'] = $data['Uwagi'];
        $createPZ['_RuchMagazynowyTempDecimal1'] = $order->_OrdersTempDecimal2;
        $createPZ['_RuchMagazynowyTempString2'] = $order->_OrdersTempString1;
        $createPZ['_RuchMagazynowyTempString1'] = $order->_OrdersTempString2;
        $createPZ['_RuchMagazynowyTempString4'] = $order->_OrdersTempString4;
        $createPZ['_RuchMagazynowyTempString5'] = $order->_OrdersTempString5;


        $ndoc = DB::selectOne("SELECT TOP 1 NrDokumentu n FROM dbo.[RuchMagazynowy] WHERE [IDRodzajuRuchuMagazynowego] = '1' AND IDMagazynu = " . $IDMagazynu . " AND year( Utworzono ) = " . date('Y') . " ORDER BY Data DESC");
        preg_match('/^PZ(.*)\/.*/', $ndoc->n, $a_ndoc);
        $createPZ['NrDokumentu'] = 'PZ' . (int) $a_ndoc[1] + 1 . '/' . date('y') . ' - ' . $data["magazin"]["Symbol"];
        // check if NrDokumentu exist in base
        // if (DB::select("select * from dbo.RuchMagazynowy where NrDokumentu = '" . $createPZ['NrDokumentu'] . "'")) {
        //     return response($createPZ['NrDokumentu'] . ' Został już utworzony', 200);
        // }
        $pz = DB::table('dbo.RuchMagazynowy')->insert($createPZ);

        // products

        // relation
        $rel = [
            'ID1' => $pz->IDRuchuMagazynowego,
            'IDType1' => 1,
            'ID2' => $data['IDRuchuMagazynowego'],
            'IDType2' => 200
        ];
        DB::table('dbo.DocumentRelations')->insert($rel);
    }
}
