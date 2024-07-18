<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function getDataForXLS(Request $request)
    {
        $data = $request->all();
        $res = [];
        $month = (int) $data['month'] + 1;
        $year = $data['year'];
        $IDWarehouse = (int)$data['IDWarehouse'];
        $current = Carbon::now();
        $lastDay =  Carbon::createFromDate($year, $month + 1, '1')->endOfMonth()->day;

        if ($current->month == $month) {
            $lastDay = $current->day;
        }
        for ($day = 1; $day <= $lastDay; $day++) {
            $date = Carbon::now()->setDate($year, $month, 22)->setTime(23, 59, 59)->format('d.m.Y H:i:s');
            $res[$day] = $this->getWarehouseData($date, $IDWarehouse);
        }
        return $res;
    }

    private function getWarehouseData($dataMax, $idMagazynu)
    {
        return DB::table('Towar as t')
            ->select([
                'DostawyMinusWydania.IdTowaru',
                't.IDMagazynu',
                't.IDGrupyTowarow',
                't.IDJednostkiMiary',
                't.Nazwa',
                't.KodKreskowy',
                'j.Nazwa as Jednostka',
                't.Archiwalny',
                't.Usluga',
                't._TowarTempDecimal2',
                DB::raw('SUM(DostawyMinusWydania.ilosc) as ilosc'),
                DB::raw('SUM(DostawyMinusWydania.Wartosc) as Wartosc'),
                DB::raw('SUM(DostawyMinusWydania.Bilans) as Bilans'),
                DB::raw('SUM(DostawyMinusWydania.ilosc) * t._TowarTempDecimal2 as m3') // Calculate m3
            ])
            ->joinSub(function ($query) use ($dataMax, $idMagazynu) {
                $query->from('ElementRuchuMagazynowego as PZ')
                    ->select([
                        't.IdTowaru',
                        DB::raw('SUM(PZ.ilosc) as Ilosc'),
                        DB::raw('SUM(ISNULL(PZ.ilosc * PZ.CenaJednostkowa, 0)) as Wartosc'),
                        DB::raw('SUM(ISNULL(PZ.ilosc * PZ.CenaJednostkowa, 0)) as Bilans')
                    ])
                    ->join('RuchMagazynowy as RuchPZ', 'RuchPZ.IDRuchuMagazynowego', '=', 'PZ.IDRuchuMagazynowego')
                    ->join('Towar as t', 't.IDTowaru', '=', 'PZ.IDTowaru')
                    ->where('t.Usluga', 0)
                    ->where('RuchPZ.Data', '<=', $dataMax)
                    ->where('RuchPZ.Data', '>=', function ($query) use ($dataMax, $idMagazynu) {
                        $query->select(DB::raw('ISNULL(MAX(r.Data), \'1900-01-01\')'))
                            ->from('RuchMagazynowy as r')
                            ->where('r.IDRodzajuRuchuMagazynowego', 12)
                            ->where('r.Operator', 1)
                            ->where('r.Data', '<=', $dataMax)
                            ->where('r.IDMagazynu', $idMagazynu)
                            ->whereColumn('r.IDMagazynu', 't.IDMagazynu');
                    })
                    ->where('RuchPZ.Operator', '>', 0)
                    ->where('PZ.Ilosc', '>', 0)
                    ->groupBy('t.IDTowaru')
                    ->unionAll(function ($query) use ($dataMax, $idMagazynu) {
                        $query->from('ZaleznosciPZWZ as PZWZ')
                            ->select([
                                't.IdTowaru',
                                DB::raw('SUM(-PZWZ.ilosc) ilosc'),
                                DB::raw('SUM(ISNULL(-PZWZ.ilosc * PZ.CenaJednostkowa, 0)) as Wartosc'),
                                DB::raw('SUM(ISNULL(-PZWZ.ilosc * WZ.CenaJednostkowa, 0)) as Bilans')
                            ])
                            ->join('ElementRuchuMagazynowego as WZ', 'WZ.IDElementuRuchuMagazynowego', '=', 'PZWZ.IDElementuWZ')
                            ->join('ElementRuchuMagazynowego as PZ', 'PZ.IDElementuRuchuMagazynowego', '=', 'PZWZ.IDElementuPZ')
                            ->join('RuchMagazynowy as RuchWZ', 'RuchWZ.IDRuchuMagazynowego', '=', 'WZ.IDRuchuMagazynowego')
                            ->join('RuchMagazynowy as RuchPZ', 'RuchPZ.IDRuchuMagazynowego', '=', 'PZ.IDRuchuMagazynowego')
                            ->join('Towar as t', 't.IDTowaru', '=', 'WZ.IDTowaru')
                            ->where('t.Usluga', 0)
                            ->where('RuchWZ.Data', '<=', $dataMax)
                            ->where('RuchWZ.Data', '>=', function ($query) use ($dataMax, $idMagazynu) {
                                $query->select(DB::raw('ISNULL(MAX(r.Data), \'1900-01-01\')'))
                                    ->from('RuchMagazynowy as r')
                                    ->where('r.IDRodzajuRuchuMagazynowego', 12)
                                    ->where('r.Operator', 1)
                                    ->where('r.Data', '<=', $dataMax)
                                    ->where('r.IDMagazynu', $idMagazynu)
                                    ->whereColumn('r.IDMagazynu', 't.IDMagazynu');
                            })
                            ->whereRaw('(RuchWZ.Operator * WZ.ilosc) < 0')
                            ->groupBy('t.IDTowaru');
                    });
            }, 'DostawyMinusWydania', 'DostawyMinusWydania.IDTowaru', '=', 't.IDTowaru')
            ->join('JednostkaMiary as j', 'j.IDJednostkiMiary', '=', 't.IDJednostkiMiary')
            ->where('t.IDMagazynu', $idMagazynu)
            ->groupBy([
                'DostawyMinusWydania.IdTowaru',
                't.IDMagazynu',
                't.IDGrupyTowarow',
                't.IDJednostkiMiary',
                't.Nazwa',
                't.KodKreskowy',
                't.Archiwalny',
                't.Usluga',
                't._TowarTempDecimal2',
                'j.Nazwa'
            ])
            ->havingRaw('SUM(DostawyMinusWydania.ilosc) > 0')
            ->get();
    }
}
