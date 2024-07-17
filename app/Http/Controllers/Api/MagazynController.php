<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class MagazynController extends Controller
{
    public function loadMagEmail()
    {
        return DB::select('SELECT [ID],[IDMagazynu] ,[Nazwa] ,[Symbol] ,em.eMailAddress ,em.cod,em.IDLokalizaciiZwrot FROM [dbo].[Magazyn] RIGHT JOIN dbo.EMailMagazyn em ON em.IDMagazyn = IDMagazynu');
    }


    public function saveMagEmail(Request $request)
    {
        $data = $request->all();
        $IDMagazyn = $data['IDMagazynu'];
        $eMailAddress = $data['eMailAddress'];
        $cod = $data['cod'];
        if (isset($data['id'])) {
            $res = DB::update('update dbo.EMailMagazyn SET IDMagazyn = ' . $IDMagazyn . ', eMailAddress = \'' . $eMailAddress . '\', cod =\'' . $cod . '\' WHERE ID = ' . (int) $data['id']);
            if ($res) {
                return 0;
            } else {
                return response('Not updated', '404');
            }
        } else {
            $res =  DB::statement('INSERT INTO dbo.EMailMagazyn (IDMagazyn,eMailAddress,cod) VALUES (' . $IDMagazyn . ',\'' . $eMailAddress . '\',\'' . $cod . '\')');
            if ($res) {
                return DB::getPdo()->lastInsertId();
            } else {
                return response('Not inserted', '404');
            }
        }
    }
    public function deleteMagEmail(Request $request)
    {
        $data = $request->all();
        $ID = $data['ID'];

        $res =  DB::table('dbo.EMailMagazyn')->where('ID', $ID)->delete($ID);
        if ($res) {
            return response('Deleted', '200');
        }
    }

public function getWarehouseData($DataMax, $IDMagazynu)
    {
        return DB::table('Towar')
        ->select('DostawyMinusWydania.IdTowaru', 'Towar.IDMagazynu', 'Towar.IDGrupyTowarow', 'Towar.IDJednostkiMiary', 'Towar.Nazwa', 'Towar.KodKreskowy', 'JednostkaMiary.Nazwa as Jednostka', 'Towar.Archiwalny', 'Towar.Usluga')
        ->join('ElementRuchuMagazynowego as PZ', 'Towar.IDTowaru', '=', 'PZ.IDTowaru')
        ->join('RuchMagazynowy as RuchPZ', 'RuchPZ.IDRuchuMagazynowego', '=', 'PZ.IDRuchuMagazynowego')
        ->leftJoin('RuchMagazynowy as RuchWZ', function ($join) use ($DataMax, $IDMagazynu) {
            $join->on('RuchWZ.IDRodzajuRuchuMagazynowego', '=', DB::raw('12'))
            ->on('RuchWZ.Operator', '=', DB::raw('1'))
                ->on('RuchWZ.Data', '<=', DB::raw('?'))
                ->on('RuchWZ.IDMagazynu', '=', DB::raw('?'))
                ->where('RuchWZ.Data', '>=', DB::raw('(SELECT max(r.Data) FROM RuchMagazynowy r WHERE r.IDRodzajuRuchuMagazynowego = 12 AND r.Operator = 1 AND r.Data <= ? AND r.IDMagazynu = ?)'))
                ->setBindings([$DataMax, $IDMagazynu, $DataMax, $IDMagazynu]);
        })
            ->where('Towar.Usluga', '=', 0)
            ->where('RuchPZ.Data', '<=', $DataMax)
            ->where('RuchPZ.Operator', '>', 0)
            ->where('PZ.Ilosc', '>', 0)
            ->groupBy('Towar.IDTowaru')
            ->unionAll(function ($query) use ($DataMax, $IDMagazynu) {
                $query->select('Towar.IDTowaru', DB::raw('sum(-PZWZ.ilosc) as ilosc'), DB::raw('sum(ISNULL(-PZWZ.ilosc*PZ.CenaJednostkowa, 0)) as Wartosc'), DB::raw('sum(ISNULL(-PZWZ.ilosc*WZ.CenaJednostkowa, 0)) as Bilans'))
                ->from('ZaleznosciPZWZ as PZWZ')
                ->join('ElementRuchuMagazynowego as WZ', 'WZ.IDElementuRuchuMagazynowego', '=', 'PZWZ.IDElementuWZ')
                ->join('ElementRuchuMagazynowego as PZ', 'PZ.IDElementuRuchuMagazynowego', '=', 'PZWZ.IDElementuPZ')
                ->join('RuchMagazynowy as RuchWZ', 'RuchWZ.IDRuchuMagazynowego', '=', 'WZ.IDRuchuMagazynowego')
                ->join('RuchMagazynowy as RuchPZ', 'RuchPZ.IDRuchuMagazynowego', '=', 'PZ.IDRuchuMagazynowego')
                ->join('Towar', 'Towar.IDTowaru', '=', 'WZ.IDTowaru')
                ->where('Towar.Usluga', '=', 0)
                ->where('RuchWZ.Data', '<=', $DataMax)
                    ->where('RuchWZ.Operator', '=', DB::raw('1'))
                    ->where('RuchWZ.Data', '>=', DB::raw('(SELECT max(r.Data) FROM RuchMagazynowy r WHERE r.IDRodzajuRuchuMagazynowego = 12 AND r.Operator = 1 AND r.Data <= ? AND r.IDMagazynu = ?)'))
                    ->where(DB::raw('(RuchWZ.Operator * WZ.ilosc)'), '<', 0)
                    ->groupBy('Towar.IDTowaru');

}