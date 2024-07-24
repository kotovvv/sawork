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
            $magID = $request->user->IDDefaultWarehouse;
            $days = (int) $day;

            // Использование параметров в запросе
            $result = DB::select(DB::raw("
            WITH SqlQuery AS (
                SELECT
                    ROW_NUMBER() OVER (ORDER BY Q.IDTowaru ASC) AS Row,
                    Q.IDTowaru,
                    Q.Nazwa_towaru,
                    Q.Kod_kreskowy,
                    jm.Nazwa AS Jednostka,
                    a.SumaIlosci AS Stan,
                    Q.Data_przyjęcia_towaru,
                    Q.Numer_dokumentu_przyjęcia,
                    Q.Data_ostatniego_wydania,
                    Q.Numer_ostatniego_wydania,
                    ISNULL(gt.Nazwa, '') AS Grupa_towarów,
                    Q.Cena_zakupu,
                    Q.Wartość_zakupu,
                    a.SumaWartosci AS Wartość,
                    Q.Cena_sprzedaży,
                    Q.Domyślna_marża,
                    Q.Stan_minimalny,
                    Q.Stan_maksymalny,
                    Q.Stan_początkowy,
                    Q.Cena_początkowa,
                    Q.Zdjęcie,
                    Q.Uwagi,
                    Q.Usługa,
                    Q.Produkt,
                    Q.Waga,
                    Q.m3,
                    Q.sku,
                    Q.Długość,
                    Q.Szerokość,
                    Q.Wysokość
                FROM (
                    SELECT
                        Towar.IDTowaru,
                        Towar.Nazwa AS Nazwa_towaru,
                        Towar.KodKreskowy AS Kod_kreskowy,
                        CASE WHEN Towar.[Data przyjęcia towaru] = '1900-01-01' THEN NULL ELSE Towar.[Data przyjęcia towaru] END AS Data_przyjęcia_towaru,
                        ISNULL(Towar.[Numer dokumentu przyjęcia], '') AS Numer_dokumentu_przyjęcia,
                        CASE WHEN Towar.[Data ostatniego wydania] = '1900-01-01' THEN NULL ELSE Towar.[Data ostatniego wydania] END AS Data_ostatniego_wydania,
                        ISNULL(Towar.[Numer ostatniego wydania], '') AS Numer_ostatniego_wydania,
                        Towar.CenaZakupu AS Cena_zakupu,
                        Towar.CenaZakupu * a.SumaIlosci AS Wartość_zakupu,
                        Towar.CenaSprzedazy AS Cena_sprzedaży,
                        Towar.DomyslnaMarza AS Domyślna_marża,
                        Towar.StanMinimalny AS Stan_minimalny,
                        Towar.StanMaksymalny AS Stan_maksymalny,
                        Towar.StanPoczatkowy AS Stan_początkowy,
                        Towar.CenaPoczatkowa AS Cena_początkowa,
                        Towar.Zdjecie AS Zdjęcie,
                        Towar.Uwagi AS Uwagi,
                        Towar.Usluga AS Usługa,
                        Towar.Produkt AS Produkt,
                        Towar._TowarTempDecimal1 AS Waga,
                        Towar._TowarTempDecimal2 AS m3,
                        Towar._TowarTempString1 AS sku,
                        Towar._TowarTempDecimal3 AS Długość,
                        Towar._TowarTempDecimal4 AS Szerokość,
                        Towar._TowarTempDecimal5 AS Wysokość
                    FROM towar
                    WHERE Towar.IDMagazynu = :magID
                ) Q
                INNER JOIN AktualnyStan a ON a.IDTowaru = Q.IDTowaru
                LEFT JOIN dbo.GrupyTowarow gt ON gt.IDGrupyTowarow = Q.IDGrupyTowarow
                LEFT JOIN dbo.JednostkaMiary jm ON jm.IDJednostkiMiary = Q.IDJednostkiMiary
                WHERE
                    a.SumaIlosci > 0
                    AND Q.Archiwalny = 0
                    AND (Q.Data_ostatniego_wydania IS NULL OR DATEDIFF(day, Q.Data_ostatniego_wydania, GETDATE()) > :days)
                    AND (Q.Data_przyjęcia_towaru IS NULL OR DATEDIFF(day, Q.Data_przyjęcia_towaru, GETDATE()) > :days)
            )
            SELECT
                [IDTowaru],
                [Nazwa_towaru],
                [Kod_kreskowy],
                [Jednostka],
                [Stan],
                [Data_przyjęcia_towaru],
                [Numer_dokumentu_przyjęcia],
                [Data_ostatniego_wydania],
                [Numer_ostatniego_wydania],
                [Grupa_towarów],
                [Cena_zakupu],
                [Wartość_zakupu],
                [Wartość],
                [Cena_sprzedaży],
                [Domyślna_marża],
                [Stan_minimalny],
                [Stan_maksymalny],
                [Stan_początkowy],
                [Cena_początkowa],
                [Zdjęcie],
                [Uwagi],
                [Usługa],
                [Produkt],
                [Waga],
                [m3],
                [sku],
                [Długość],
                [Szerokość],
                [Wysokość]
            FROM SqlQuery
        "), [
                'magID' => $magID,
                'days' => $days
            ]);

            return $result;
        }

        return response('No default warehouse', 404);
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