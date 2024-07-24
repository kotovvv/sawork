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

    public function getDataNotActivProduct(Request $request, $day, $idwarehouse)
    {
        $user = $request->user;
        $a_mag = collect(DB::table('UprawnieniaDoMagazynow')->where('IDUzytkownika', $user->IDUzytkownika)->where('Uprawniony', 1)->pluck('IDMagazynu'))->toArray();
        if (isset($idwarehouse) && in_array($idwarehouse, $a_mag)) {
            $MagID = $idwarehouse;
            $days = (int) $day;

            // Параметры
            // $MagID = 10;
            // $days = 30;
            $AllowZL = 0;
            $AllowDiscounts = 0;
            $_Lang = 'PL';
            $_UserID = 4;
            $_WarehouseID = 10;
            $_DefaultCurrencyID = 1;
            $_IsPricesModeNet = false;
            $_CenaJakoMarza = true;

            $subQuery = DB::table('towar')
                ->select([
                    'towar.IDTowaru',
                    DB::raw("ISNULL((SELECT MAX(pz.Data) FROM elementRuchuMagazynowego epz
            INNER JOIN RuchMagazynowy pz ON pz.IDRuchuMagazynowego = epz.IDRuchuMagazynowego
            WHERE IDTowaru = towar.IDTowaru AND pz.Operator * epz.ilosc > 0
                AND ($AllowDiscounts = 1 OR pz.IDRodzajuRuchuMagazynowego <> 8)
                AND ($AllowZL = 1 OR pz.IDRodzajuRuchuMagazynowego <> 27)), '') AS [Data przyjęcia towaru]"),
                    DB::raw("(SELECT TOP 1 pz.NrDokumentu FROM elementRuchuMagazynowego epz
            INNER JOIN RuchMagazynowy pz ON pz.IDRuchuMagazynowego = epz.IDRuchuMagazynowego
            WHERE IDTowaru = towar.IDTowaru AND pz.Operator * epz.ilosc > 0
                AND ($AllowDiscounts = 1 OR pz.IDRodzajuRuchuMagazynowego <> 8)
                AND ($AllowZL = 1 OR pz.IDRodzajuRuchuMagazynowego <> 27)
            ORDER BY pz.Data DESC) AS [Numer dokumentu przyjęcia]"),
                    DB::raw("(SELECT MAX(wz.Data) FROM elementRuchuMagazynowego ewz
            INNER JOIN RuchMagazynowy wz ON wz.IDRuchuMagazynowego = ewz.IDRuchuMagazynowego
            WHERE IDTowaru = towar.IDTowaru AND wz.Operator * ewz.ilosc < 0
                AND ($AllowDiscounts = 1 OR wz.IDRodzajuRuchuMagazynowego <> 8)
                AND ($AllowZL = 1 OR wz.IDRodzajuRuchuMagazynowego <> 27)) AS [Data ostatniego wydania]"),
                    DB::raw("(SELECT TOP 1 wz.NrDokumentu FROM elementRuchuMagazynowego ewz
            INNER JOIN RuchMagazynowy wz ON wz.IDRuchuMagazynowego = ewz.IDRuchuMagazynowego
            WHERE IDTowaru = towar.IDTowaru AND wz.Operator * ewz.ilosc < 0
                AND ($AllowDiscounts = 1 OR wz.IDRodzajuRuchuMagazynowego <> 8)
                AND ($AllowZL = 1 OR wz.IDRodzajuRuchuMagazynowego <> 27)
            ORDER BY wz.Data DESC) AS [Numer ostatniego wydania]")
                ])
                ->where('towar.IDMagazynu', $MagID);

            $query = DB::table(DB::raw("({$subQuery->toSql()}) as Q"))
                ->mergeBindings($subQuery)
                ->join('AktualnyStan as a', 'a.IDTowaru', '=', 'Q.IDTowaru')
                ->join('Towar', 'Towar.IDTowaru', '=', 'Q.IDTowaru')
                ->leftJoin('GrupyTowarow as gt', 'gt.IDGrupyTowarow', '=', 'Towar.IDGrupyTowarow')
                ->leftJoin('JednostkaMiary as jm', 'jm.IDJednostkiMiary', '=', 'Towar.IDJednostkiMiary')
                ->select([
                    'Towar.IDTowaru',
                    'Towar.Nazwa as Nazwa towaru',
                    'Towar.KodKreskowy as Kod kreskowy',
                    'jm.Nazwa as Jednostka',
                    'a.SumaIlosci as Stan',
                    DB::raw("CASE WHEN Q.[Data przyjęcia towaru] = '1900-01-01' THEN NULL ELSE Q.[Data przyjęcia towaru] END as [Data przyjęcia towaru]"),
                    DB::raw("ISNULL(Q.[Numer dokumentu przyjęcia], '') as [Numer dokumentu przyjęcia]"),
                    DB::raw("CASE WHEN Q.[Data ostatniego wydania] = '1900-01-01' THEN NULL ELSE Q.[Data ostatniego wydania] END as [Data ostatniego wydania]"),
                    DB::raw("ISNULL(Q.[Numer ostatniego wydania], '') as [Numer ostatniego wydania]"),
                    DB::raw("ISNULL(gt.Nazwa, 0) as [Grupa towarów]"),
                    'Towar.CenaZakupu as [Cena zakupu]',
                    DB::raw('Towar.CenaZakupu * a.SumaIlosci as [Wartość zakupu]'),
                    'a.SumaWartosci as Wartość',
                    'Towar.CenaSprzedazy as [Cena sprzedaży]',
                    'Towar.DomyslnaMarza as [Domyślna marża]',
                    'Towar.StanMinimalny as [Stan minimalny]',
                    'Towar.StanMaksymalny as [Stan maksymalny]',
                    'Towar.StanPoczatkowy as [Stan początkowy]',
                    'Towar.CenaPoczatkowa as [Cena początkowa]',
                    'Towar.Zdjecie as Zdjęcie',
                    'Towar.Uwagi as Uwagi',
                    'Towar.Usluga as Usługa',
                    'Towar.Produkt as Produkt',
                    'Towar._TowarTempDecimal1 as Waga',
                    'Towar._TowarTempDecimal2 as m3',
                    'Towar._TowarTempString1 as sku',
                    'Towar._TowarTempDecimal3 as Długość',
                    'Towar._TowarTempDecimal4 as Szerokość',
                    'Towar._TowarTempDecimal5 as Wysokość'
                ])
                ->where('a.SumaIlosci', '>', 0)
                ->where('Towar.Archiwalny', '=', 0)
                ->where(function ($query) use ($days) {
                    $query->whereNull('Data ostatniego wydania')
                        ->orWhereRaw('DATEDIFF(day, Q.[Data ostatniego wydania], getdate()) > ?', [$days]);
                })
                ->where(function ($query) use ($days) {
                    $query->whereNull('Data przyjęcia towaru')
                        ->orWhereRaw('DATEDIFF(day, Q.[Data przyjęcia towaru], getdate()) > ?', [$days]);
                });

            $result = $query->get();

            return $result;
        }

        return response('No default warehouse', 404);
    }

    public function getDataForXLSDay(Request $request, $day, $idwarehouse)
    {
        $user = $request->user;
        $a_mag = collect(DB::table('UprawnieniaDoMagazynow')->where('IDUzytkownika', $user->IDUzytkownika)->where('Uprawniony', 1)->pluck('IDMagazynu'))->toArray();
        if (isset($idwarehouse) && in_array($idwarehouse, $a_mag)) {
            $res = [];
            $date = Carbon::now()->parse($day)->setTime(23, 59, 59)->format('d/m/Y H:i:s');

            $res[Carbon::now()->parse($day)->format('d-m-Y')] = $this->getWarehouseData($date, $request->user->IDDefaultWarehouse);
            return $res;
        }
        return response('No warehause', 404);
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