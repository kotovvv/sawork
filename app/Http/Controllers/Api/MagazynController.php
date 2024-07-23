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

    public function getDataNotActivProduct(Request $request, $day)
    {
        if (isset($request->user->IDDefaultWarehouse)) {
            $res = [];
            $res =  DB::select('WITH SqlQuery AS ( SELECT  ROW_NUMBER() OVER( ORDER BY [IDTowaru] ASC ) AS Row,  * FROM ( select Towar.IDTowaru,
	Towar.[Nazwa] as \'Nazwa towaru\',
	Towar.[KodKreskowy] as \'Kod kreskowy\',
	jm.Nazwa as \'Jednostka\',
	a.SumaIlosci as \'Stan\',
	CASE WHEN [Data przyjęcia towaru] = \'1900-01-01\' THEN NULL ELSE [Data przyjęcia towaru] END as \'Data przyjęcia towaru\',
	ISNULL([Numer dokumentu przyjęcia], \'\') as \'Numer dokumentu przyjęcia\',
	CASE WHEN [Data ostatniego wydania] = \'1900-01-01\' THEN NULL ELSE [Data ostatniego wydania] END as \'Data ostatniego wydania\',
	ISNULL([Numer ostatniego wydania], \'\') as \'Numer ostatniego wydania\',
	ISNULL(gt.Nazwa, 0) as \'Grupa towarów\',
	[CenaZakupu] as \'Cena zakupu\',
	[CenaZakupu] * a.SumaIlosci as \'Wartość zakupu\',
	a.SumaWartosci as \'Wartość\', -- zad. 1195
	[CenaSprzedazy] as \'Cena sprzedaży\',
	[DomyslnaMarza] as \'Domyślna marża\',
	[StanMinimalny] as \'Stan minimalny\',
	[StanMaksymalny] as \'Stan maksymalny\',
	[StanPoczatkowy] as \'Stan początkowy\',
	[CenaPoczatkowa] as \'Cena początkowa\',
	[Zdjecie] as \'Zdjęcie\',
	[Uwagi] as \'Uwagi\',
	Towar.[Usluga] as \'Usługa\',
	Towar.[Produkt] as \'Produkt\'
	, Towar.[_TowarTempDecimal1] AS \'Waga\' , Towar.[_TowarTempDecimal2] AS \'m3\' , Towar.[_TowarTempString1] AS \'sku\' , Towar.[_TowarTempDecimal3] AS \'Długość\' , Towar.[_TowarTempDecimal4] AS \'Szerokość\' , Towar.[_TowarTempDecimal5] AS \'Wysokość\'
from
(
	select Towar.IDTowaru,
		ISNULL((select MAX(pz.Data) from elementRuchuMagazynowego epz
			inner join RuchMagazynowy pz ON pz.IDRuchuMagazynowego = epz.IDRuchuMagazynowego
			where IDTowaru = Towar.IDTowaru AND pz.Operator * epz.ilosc > 0
				and (0 = 1 or pz.IDRodzajuRuchuMagazynowego <> 8)
				and (0 = 1 or pz.IDRodzajuRuchuMagazynowego <> 27)
			), \'\') as \'Data przyjęcia towaru\',
		(select TOP 1 pz.NrDokumentu from elementRuchuMagazynowego epz
			inner join RuchMagazynowy pz ON pz.IDRuchuMagazynowego = epz.IDRuchuMagazynowego
			where IDTowaru = Towar.IDTowaru AND pz.Operator * epz.ilosc > 0
				and (0 = 1 or pz.IDRodzajuRuchuMagazynowego <> 8)
				and (0 = 1 or pz.IDRodzajuRuchuMagazynowego <> 27)
			order by pz.Data desc) as \'Numer dokumentu przyjęcia\',
		(select MAX(wz.Data) from elementRuchuMagazynowego ewz
			inner join RuchMagazynowy wz ON wz.IDRuchuMagazynowego = ewz.IDRuchuMagazynowego
			where IDTowaru = Towar.IDTowaru AND wz.Operator * ewz.ilosc < 0
				and (0 = 1 or wz.IDRodzajuRuchuMagazynowego <> 8)
				and (0 = 1 or wz.IDRodzajuRuchuMagazynowego <> 27)
		) as \'Data ostatniego wydania\',
		(select TOP 1 wz.NrDokumentu from elementRuchuMagazynowego ewz
			inner join RuchMagazynowy wz ON wz.IDRuchuMagazynowego = ewz.IDRuchuMagazynowego
			where IDTowaru = Towar.IDTowaru AND wz.Operator * ewz.ilosc < 0
				and (0 = 1 or wz.IDRodzajuRuchuMagazynowego <> 8)
				and (0 = 1 or wz.IDRodzajuRuchuMagazynowego <> 27)
			order by wz.Data desc
		) as \'Numer ostatniego wydania\'
	from towar
	where Towar.IDMagazynu = 10
) Q
inner join AktualnyStan a on a.IDTowaru = Q.IDTowaru
inner join Towar on Towar.IDTowaru = Q.IDTowaru
left join dbo.GrupyTowarow gt on gt.IDGrupyTowarow = Towar.[IDGrupyTowarow]
left join dbo.JednostkaMiary jm on jm.[IDJednostkiMiary] = Towar.[IDJednostkiMiary]
where
	a.Sumailosci > 0 and Towar.Archiwalny = 0 and
	([Data ostatniego wydania] IS NULL OR  DATEDIFF(day, [Data ostatniego wydania], getdate()) > 30) and
	([Data przyjęcia towaru] IS NULL OR DATEDIFF(day, [Data przyjęcia towaru], getdate()) > 30)
 ) AS SubQuery  ) SELECT "IDTowaru", "Nazwa towaru", "Kod kreskowy", "Jednostka", "Stan", "Data przyjęcia towaru", "Numer dokumentu przyjęcia", "Data ostatniego wydania", "Numer ostatniego wydania", "Grupa towarów", "Cena zakupu", "Wartość zakupu", "Wartość", "Cena sprzedaży", "Domyślna marża", "Stan minimalny", "Stan maksymalny", "Stan początkowy", "Cena początkowa", "Zdjęcie", "Uwagi", "Usługa", "Produkt", "Waga", "m3", "sku", "Długość", "Szerokość", "Wysokość" FROM SqlQuery ');
            return response()->json($res, 200, [], JSON_UNESCAPED_UNICODE);
        }
        return response('No default warehause', 404);
    }

    public function getDataForXLSDay(Request $request, $day)
    {
        if (isset($request->user->IDDefaultWarehouse)) {
            $res = [];
            $date = Carbon::now()->parse($day)->setTime(23, 59, 59)->format('d/m/Y H:i:s');

            $res[Carbon::now()->parse($day)->format('d-m-Y')] = $this->getWarehouseData($date, $request->user->IDDefaultWarehouse);
            return $res;
        }
        return response('No default warehause', 404);
    }


    public function getDataForXLS(Request $request)
    {
        $data = $request->all();
        $res = [];
        $month = (int) $data['month'] + 1;
        $year = $data['year'];
        $IDWarehouse = (int)$data['IDWarehouse'];
        $current = Carbon::now();
        $lastDay =  Carbon::createFromDate($year, $month, '1')->endOfMonth()->day;

        if ($current->month == $month) {
            $lastDay = $current->day;
        }
        for ($day = 1; $day <= $lastDay; $day++) {
            // $date = Carbon::now()->setDate($year, $month, 22)->setTime(23, 59, 59)->format('d.m.Y H:i:s');
            $date = Carbon::now()->setDate($year, $month, $day)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            $res[$day] = $this->getWarehouseData($date, $IDWarehouse);
        }
        return $res;
    }

    private function getWarehouseData($dataMax, $idMagazynu)
    {
        return DB::table('Towar as t')
            ->select([
                //'DostawyMinusWydania.IdTowaru',
                //'t.IDMagazynu',
                // 't.IDGrupyTowarow',
                //'t.IDJednostkiMiary',
                't.Nazwa',
                't.KodKreskowy',
                // 'j.Nazwa as Jednostka',
                //'t.Archiwalny',
                //'t.Usluga',
                't._TowarTempString1 as sku',
                DB::raw('SUM(DostawyMinusWydania.ilosc) as stan'),
                DB::raw('SUM(DostawyMinusWydania.Wartosc) as Wartosc'),
                // DB::raw('SUM(DostawyMinusWydania.Bilans) as Bilans'),
                DB::raw('SUM(DostawyMinusWydania.ilosc) * t._TowarTempDecimal2 as m3xstan') // Calculate m3
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
                '_TowarTempString1',
                'j.Nazwa'
            ])
            ->havingRaw('SUM(DostawyMinusWydania.ilosc) > 0')
            ->get();
    }
}
