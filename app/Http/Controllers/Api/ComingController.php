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
        return DB::table('dbo.RuchMagazynowy')->select('IDRuchuMagazynowego', 'Data', 'NrDokumentu', 'WartoscDokumentu', 'ID1')->leftJoin('DocumentRelations', 'ID2', '=', 'IDRuchuMagazynowego')->where('IDType2', 200)->where('IDRodzajuRuchuMagazynowego', 200)->where('IDMagazynu', $IDMagazynu)->orderBy('Data', 'DESC')->get();
    }

    public function createPZ(Request $request)
    {
        $data = $request->all();
        $IDMagazynu = $data['IDMagazynu'];
        $Symbol = DB::table('dbo.Magazyn')->where('IDMagazynu', $IDMagazynu)->first()->Symbol;
        $IDKontrahenta = DB::table('dbo.EMailMagazyn')->where('IDMagazyn', $IDMagazynu)->where('IDKontrahenta', '>', 0)->first()->IDKontrahenta;

        // create PZ
        $createPZ = [];
        $createPZ['IDMagazynu'] = $IDMagazynu;
        $createPZ['Data'] = date('Y/m/d H:i:s');
        $createPZ['IDRodzajuRuchuMagazynowego'] = 1;
        $createPZ['IDUzytkownika'] = 1;
        $createPZ['Operator'] = 1;
        $createPZ['IDCompany'] = 1;
        $createPZ['IDKontrahenta'] = $IDKontrahenta;
        $createPZ['Uwagi'] = $data['Uwagi'];



        $ndoc = DB::selectOne("SELECT TOP 1 NrDokumentu n FROM dbo.[RuchMagazynowy] WHERE [IDRodzajuRuchuMagazynowego] = '1' AND IDMagazynu = " . $IDMagazynu . " AND year( Utworzono ) = " . date('Y') . " ORDER BY Data DESC");
        preg_match('/^PZ(.*)\/.*/', $ndoc->n, $a_ndoc);
        $createPZ['NrDokumentu'] = 'PZ' . (int) $a_ndoc[1] + 1 . '/' . date('y') . ' - ' . $Symbol;
        // check if NrDokumentu exist in base
        if (DB::table('dbo.RuchMagazynowy')->where('NrDokumentu', $createPZ['NrDokumentu'])->exists()) {
            return response($createPZ['NrDokumentu'] . ' Został już utworzony', 200);
        }

        DB::table('dbo.RuchMagazynowy')->insert($createPZ);
        $pzID = DB::table('dbo.RuchMagazynowy')->where('NrDokumentu', $createPZ['NrDokumentu'])->first()->IDRuchuMagazynowego;

        // products
        $products = DB::table('dbo.ElementRuchuMagazynowego')->select('Ilosc', 'Uwagi', 'CenaJednostkowa', 'IDTowaru', 'Uzytkownik')->where('IDRuchuMagazynowego', $data['IDRuchuMagazynowego'])->get();
        $productsArray = [];
        foreach ($products as $product) {
            $productsArray[] = [
                'IDRuchuMagazynowego' => $pzID,
                'Ilosc' => $product->Ilosc,
                'Uwagi' => $product->Uwagi,
                'CenaJednostkowa' => $product->CenaJednostkowa,
                'IDTowaru' => $product->IDTowaru,
                'Uzytkownik' => $product->Uzytkownik
            ];
        }

        // Ensure the parent record exists before inserting child records
        if (DB::table('dbo.RuchMagazynowy')->where('IDRuchuMagazynowego', $pzID)->exists()) {
            DB::table('dbo.ElementRuchuMagazynowego')->insert($productsArray);
        } else {
            return response('Parent record does not exist', 400);
        }


        // relation
        $rel = [
            'ID1' => $pzID,
            'IDType1' => 1,
            'ID2' => $data['IDRuchuMagazynowego'],
            'IDType2' => 200
        ];
        DB::table('dbo.DocumentRelations')->insert($rel);
        return response($createPZ['NrDokumentu'] . ' Został już utworzony', 200);
    }
}
