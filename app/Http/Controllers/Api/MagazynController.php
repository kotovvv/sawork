<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    private function canUseWarehouse($user, $idwarehouse)
    {
        if ($user && (int) $idwarehouse > 0) {
            $a_mag = collect(DB::table('UprawnieniaDoMagazynow')->where('IDUzytkownika', $user->IDUzytkownika)->where('Uprawniony', 1)->pluck('IDMagazynu'))->toArray();
            return in_array($idwarehouse, $a_mag);
        }
        return false;
    }

    public function getDataNotActivProduct(Request $request, $day, $idwarehouse)
    {
        if ($this->canUseWarehouse($request->user, $idwarehouse)) {
            $MagID = (int) $idwarehouse;
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
                    'Towar._TowarTempString1 as sku',
                    //'jm.Nazwa as Jednostka',
                    DB::raw('CAST(a.SumaIlosci AS INT)as Stan'),
                    DB::raw("CASE WHEN Q.[Data przyjęcia towaru] = '1900-01-01' THEN NULL ELSE Q.[Data przyjęcia towaru] END as [Data przyjęcia towaru]"),
                    DB::raw("ISNULL(Q.[Numer dokumentu przyjęcia], '') as [Numer dokumentu przyjęcia]"),
                    DB::raw("CASE WHEN Q.[Data ostatniego wydania] = '1900-01-01' THEN NULL ELSE Q.[Data ostatniego wydania] END as [Data ostatniego wydania]"),
                    DB::raw("ISNULL(Q.[Numer ostatniego wydania], '') as [Numer ostatniego wydania]"),
                    //DB::raw("ISNULL(gt.Nazwa, 0) as [Grupa towarów]"),
                    //DB::raw('ROUND(Towar.CenaZakupu, 2) as [Cena zakupu]'),
                    DB::raw("DATEDIFF(DAY,ISNULL([Data ostatniego wydania],[Data przyjęcia towaru]),GETDATE()) AS [Ilość dni]"),
                    // DB::raw('Towar.CenaZakupu * a.SumaIlosci as [Wartość zakupu]'),
                    // 'a.SumaWartosci as Wartość',
                    // 'Towar.CenaSprzedazy as [Cena sprzedaży]',
                    // 'Towar.DomyslnaMarza as [Domyślna marża]',
                    // 'Towar.StanMinimalny as [Stan minimalny]',
                    // 'Towar.StanMaksymalny as [Stan maksymalny]',
                    // 'Towar.StanPoczatkowy as [Stan początkowy]',
                    // 'Towar.CenaPoczatkowa as [Cena początkowa]',
                    // //'Towar.Zdjecie as Zdjęcie',
                    // 'Towar.Uwagi as Uwagi',
                    // 'Towar.Usluga as Usługa',
                    // 'Towar.Produkt as Produkt',
                    // 'Towar._TowarTempDecimal1 as Waga',
                    // 'Towar._TowarTempDecimal2 as m3',

                    // 'Towar._TowarTempDecimal3 as Długość',
                    // 'Towar._TowarTempDecimal4 as Szerokość',
                    // 'Towar._TowarTempDecimal5 as Wysokość'
                ])
                ->where('a.SumaIlosci', '>', 0)
                ->where('Towar.Archiwalny',  0)
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
        if ($this->canUseWarehouse($request->user, $idwarehouse)) {
            $res = [];
            //$date = Carbon::now()->parse($day)->setTime(23, 59, 59)->format('d/m/Y H:i:s');
            // $date = Carbon::now()->parse($day)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            $date = Carbon::now()->parse($day)->setTime(23, 59, 59)->format('m.d.Y H:i:s');

            $res[Carbon::now()->parse($day)->format('d-m-Y')] = $this->getWarehouseData($date, $idwarehouse);
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
                't.IDTowaru',
                DB::raw('t.Nazwa as "Nazwa towaru"'),
                't.KodKreskowy',
                DB::raw('t._TowarTempString1 as sku'),
                DB::raw('ROUND(SUM(DostawyMinusWydania.Wartosc), 2) as wartosc'),
                DB::raw('ISNULL(SUM(DostawyMinusWydania.ilosc) * t._TowarTempDecimal2, 0) as m3xstan'),
                DB::raw('CAST(SUM(DostawyMinusWydania.ilosc) AS INT) as stan'),
                DB::raw('CAST(ISNULL(ol.ProductCountWithoutWZ, 0) AS INT) as rezerv'),
                DB::raw('CAST(ISNULL(SUM(DostawyMinusWydania.ilosc) - ISNULL(ol.ProductCountWithoutWZ, 0), 0) AS INT) as pozostać')
            ])
            ->joinSub(function ($query) use ($dataMax, $idMagazynu) {
                $query->from('ElementRuchuMagazynowego as PZ')
                    ->select([
                        't.IdTowaru',
                        DB::raw('SUM(PZ.ilosc) as Ilosc'),
                        DB::raw('ROUND(SUM(ISNULL(PZ.ilosc * PZ.CenaJednostkowa, 0)),2) as Wartosc'),
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
            ->leftJoinSub(function ($query) {
                $query->from('OrderLines as ol')
                    ->select([
                        'ol.IDItem as IDTowaru',
                        DB::raw('ISNULL(SUM(ol.Quantity), 0) as ProductCountWithoutWZ')
                    ])
                    ->join('Orders as o', 'o.IDOrder', '=', 'ol.IDOrder')
                    ->join('OrderStatus as os', 'os.IDOrderStatus', '=', 'o.IDOrderStatus')
                    ->whereNull('os.SetUpAction')
                    ->orWhere('os.SetUpAction', '<>', 256)
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('DocumentRelations as dr')
                            ->whereColumn('dr.ID2', 'o.IDOrder')
                            ->where('dr.IDType2', 15)
                            ->where('dr.IDType1', 2);
                    })
                    ->groupBy('ol.IDItem');
            }, 'ol', 'ol.IDTowaru', '=', 't.IDTowaru')
            ->join('JednostkaMiary as j', 'j.IDJednostkiMiary', '=', 't.IDJednostkiMiary')
            ->where('t.IDMagazynu', $idMagazynu)
            ->groupBy([
                'DostawyMinusWydania.IdTowaru',
                't.IDTowaru',
                't.IDMagazynu',
                't.IDGrupyTowarow',
                't.IDJednostkiMiary',
                't.Nazwa',
                't.KodKreskowy',
                't.Archiwalny',
                't.Usluga',
                't._TowarTempDecimal2',
                '_TowarTempString1',
                'j.Nazwa',
                'ol.ProductCountWithoutWZ'
            ])
            ->havingRaw('SUM(DostawyMinusWydania.ilosc) > 0')
            ->get();
    }

    public function setClientPriceCondition(Request $request)
    {
        $data = $request->all();
        $idwarehouse = (int) $data['IDWarehouse'];
        $id_condition = $data['id_condition'];
        $forinsert = [];
        foreach ($id_condition as $value) {
            $forinsert[] = ['IDMagazynu' => $idwarehouse, 'condition_id' => $value];
        }
        if ($this->canUseWarehouse($request->user, $idwarehouse)) {
            DB::table('dbo.client_price_conditions')->where('IDMagazynu', $idwarehouse)->delete();
            DB::table('dbo.client_price_conditions')->insert($forinsert);
            return response('Set price condition', 200);
        }
        return response('No warehause', 431);
    }

    public function getClientPriceCondition(Request $request, $idwarehouse)
    {
        if ($this->canUseWarehouse($request->user, $idwarehouse)) {
            return DB::table('dbo.client_price_conditions')->where('IDMagazynu', $idwarehouse)->get();
        }
        return response('No warehause', 431);
    }

    public function getPriceCondition()
    {
        return DB::table('dbo.price_conditions')->orderBy('max_value', 'ASC')->get();
    }

    public function getReportTarif(Request $request, $month, $idwarehouse)
    {
        $now = Carbon::now();
        if ($this->canUseWarehouse($request->user, $idwarehouse)) {
            $year = $now->year;
            if ($now->month == 0 && $month == 11) {
                $year = $now->year - 1;
            }
            $startDay = $now->createFromDate($year, $month + 1, '1')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
            $endDay = $now->createFromDate($year, $month + 1, '1')->endOfMonth()->setTime(23, 59, 59)->format('Y-m-d H:i:s');

            $query = "SELECT rm.IDRuchuMagazynowego,NrDokumentu,Data,IDKontrahenta,prod.volume,CAST(pc.price AS DECIMAL(5,2)) price FROM dbo.RuchMagazynowy rm JOIN (SELECT sum(Ilosc * t._TowarTempDecimal2) volume,erm.IDRuchuMagazynowego  FROM ElementRuchuMagazynowego erm LEFT JOIN Towar t ON t.IDTowaru = erm.IDTowaru GROUP BY erm.IDRuchuMagazynowego) prod ON prod.IDRuchuMagazynowego = rm.IDRuchuMagazynowego JOIN     client_price_conditions cpc ON rm.IDMagazynu = cpc.IDMagazynu JOIN price_conditions pc ON cpc.condition_id = pc.condition_id WHERE rm.IDRodzajuRuchuMagazynowego = 2 AND Data >= :startDay AND Data <= :endDay AND rm.IDMagazynu = :magID AND prod.volume >= pc.min_value AND prod.volume < pc.max_value order by Data ASC";

            $result = DB::select($query, [
                'magID' => $idwarehouse,
                'startDay' => $startDay,
                'endDay' => $endDay,
            ]);
            return $result;
        }
        return response('No warehause', 404);
    }

    public function getProductHistory($IDTowaru)
    {
        //$product = DB::table('towar')->where('IDTowaru', $IDTowaru)->first();
        $warehouseId = DB::table('towar')->where('IDTowaru', $IDTowaru)->value('IDMagazynu');


        $boDateResult = DB::select('SELECT dbo.MostRecentOBDateScalar(?, ?) as BODate', [now(), $warehouseId]);
        $boDate = $boDateResult[0]->BODate;

        // Fetch movements and handle stock calculations
        $movements = DB::table('RuchMagazynowy as r')
            ->leftJoin('Kontrahent', 'r.IDKontrahenta', '=', 'Kontrahent.IDKontrahenta')
            ->join('RodzajRuchuMagazynowego', 'r.IDRodzajuRuchuMagazynowego', '=', 'RodzajRuchuMagazynowego.IDRodzajuRuchuMagazynowego')
            ->leftJoin('PrzesunieciaMM as pmm', 'pmm.IDRuchuMagazynowegoDo', '=', 'r.IDRuchuMagazynowego')
            ->whereExists(function ($query) use ($IDTowaru) {
                $query->select(DB::raw(1))
                    ->from('ElementRuchuMagazynowego as e_X')
                    ->whereColumn('e_X.IDRuchuMagazynowego', 'r.IDRuchuMagazynowego')
                    ->where('e_X.IDTowaru', $IDTowaru);
            })
            ->where('r.Data', '>=', $boDate)
            ->where('r.Operator', '<>', 0)
            ->where('r.IDRodzajuRuchuMagazynowego', '<>', 27)
            ->select([
                'r.IDRuchuMagazynowego as movement_id',
                DB::raw('CASE WHEN pmm.IDPrzesunieciaMM IS NULL THEN r.Data ELSE DATEADD(ms, 333, r.Data) END AS date'),
                'r.Uwagi as remarks',
                'r.IDRodzajuRuchuMagazynowego as movement_type_id',
                'r.IDMagazynu as warehouse_id',
                'r.NrDokumentu as document_number',
                'r.IDKontrahenta as contractor_id',
                'Kontrahent.Nazwa as contractor_name',
                'RodzajRuchuMagazynowego.Nazwa as movement_name',
                DB::raw('(r.Operator * (SELECT SUM(e_X.Ilosc) FROM ElementRuchuMagazynowego e_X WHERE e_X.IDTowaru = ' . $IDTowaru . ' AND e_X.IDRuchuMagazynowego = r.IDRuchuMagazynowego)) AS quantity'),
                DB::raw('0 AS stock_level')
                // ,
                //                 DB::raw('ROUND((SELECT CASE WHEN SUM(Ilosc) = 0 THEN 0 ELSE SUM(CenaJednostkowa * Ilosc) / SUM(Ilosc) END FROM ElementRuchuMagazynowego WHERE IDTowaru = ' . $IDTowaru . ' AND IDRuchuMagazynowego = r.IDRuchuMagazynowego),2) AS unit_price')
                // ,
                // DB::raw('ROUND((SELECT CASE WHEN SUM(Ilosc) = 0 THEN 0 ELSE SUM(CenaJednostkowa * Ilosc) / SUM(Ilosc) END FROM ElementRuchuMagazynowego WHERE IDTowaru = ' . $IDTowaru . ' AND IDRuchuMagazynowego = r.IDRuchuMagazynowego) * (r.Operator * (SELECT SUM(e_X.Ilosc) FROM ElementRuchuMagazynowego e_X WHERE e_X.IDTowaru = ' . $IDTowaru . ' AND e_X.IDRuchuMagazynowego = r.IDRuchuMagazynowego)),2) as wartosc')
            ])
            ->orderBy('date', 'DESC')
            ->get();

        // Updating stock levels in memory
        $stockLevel = 0;
        foreach ($movements as $movement) {
            $stockLevel += $movement->quantity;
            $movement->stock_level = $stockLevel;
        }

        return response()->json($movements);
    }

    public function getOborot(Request $request)
    {
        $data = $request->all();
        $dataMin = $data['dataMin'];
        $dataMax = $data['dataMax'];
        $IDMagazynu = $data['IDMagazynu'];
        $IDKontrahenta = $data['IDKontrahenta'];
        $AllowDiscountDocs = 1;
        $AllowZLDocs = 1;


        $results = DB::table('Towar as t')
            ->select([
                't.IDTowaru',
                't.Nazwa as Towar',
                't.KodKreskowy as KodKreskowy',
                DB::raw('ISNULL(MAX(StanPoczatkowy.ilosc), 0) as StanPoczatkowy'),
                DB::raw('ISNULL(MAX(StanPoczatkowy.wartosc), 0) as WartośćPoczątkowa'),
                DB::raw('ISNULL(SUM(el.Ilosc * CASE WHEN RuchMagazynowy.Operator * el.Ilosc > 0 THEN 1 ELSE 0 END), 0) as IlośćWchodząca'),
                DB::raw('ISNULL(SUM(el.Ilosc * CASE WHEN RuchMagazynowy.Operator * el.Ilosc > 0 THEN 1 ELSE 0 END * el.CenaJednostkowa), 0) as WartośćWchodząca'),
                DB::raw('ISNULL(SUM(ABS(el.Ilosc) * CASE WHEN RuchMagazynowy.Operator * el.Ilosc < 0 THEN 1 ELSE 0 END), 0) as IlośćWychodząca'),
                DB::raw('ISNULL(SUM(ABS(el.Ilosc) * CASE WHEN RuchMagazynowy.Operator * el.Ilosc < 0 THEN 1 ELSE 0 END * el.CenaJednostkowa), 0) as WartośćWychodząca'),
                DB::raw('ISNULL(MAX(StanKoncowy.ilosc), 0) as StanKoncowy'),
                DB::raw('ISNULL(MAX(StanKoncowy.wartosc), 0) as WartośćKoncowa'),
                DB::raw('ISNULL(SUM(el.Ilosc * CASE WHEN RuchMagazynowy.Operator * el.Ilosc > 0 THEN 1 ELSE 0 END), 0) - ISNULL(SUM(ABS(el.Ilosc) * CASE WHEN RuchMagazynowy.Operator * el.Ilosc < 0 THEN 1 ELSE 0 END), 0) as BilansIlości'),
                DB::raw('ISNULL(SUM(el.Ilosc * CASE WHEN RuchMagazynowy.Operator * el.Ilosc > 0 THEN 1 ELSE 0 END * el.CenaJednostkowa) - SUM(ABS(el.Ilosc) * CASE WHEN RuchMagazynowy.Operator * el.Ilosc < 0 THEN 1 ELSE 0 END * el.CenaJednostkowa), 0) as BilansWartości')
            ])
            ->join('JednostkaMiary', 'JednostkaMiary.IDJednostkiMiary', '=', 't.IDJednostkiMiary')
            ->join('Magazyn', 't.IDMagazynu', '=', 'Magazyn.IDMagazynu')
            ->join(DB::raw('dbo.MostRecentOBDate(?) as BO', [$dataMax]), 't.IDMagazynu', '=', 'BO.IDMagazynu')
            ->leftJoin(DB::raw('dbo.StanyWDniu(?) as StanPoczatkowy', [$dataMin]), 'StanPoczatkowy.IDTowaru', '=', 't.IDTowaru')
            ->leftJoin(DB::raw('dbo.StanyWDniu(?) as StanKoncowy', [$dataMax]), 'StanKoncowy.IDTowaru', '=', 't.IDTowaru')
            ->leftJoin('ElementRuchuMagazynowego as el', 'el.IDTowaru', '=', 't.IDTowaru')
            ->leftJoin('RuchMagazynowy', function ($join) use ($dataMin, $dataMax) {
                $join->on('el.IDRuchuMagazynowego', '=', 'RuchMagazynowy.IDRuchuMagazynowego')
                    ->whereBetween('RuchMagazynowy.Data', [$dataMin,  $dataMax]);
            })
            ->leftJoin('GrupyTowarow', 't.IDGrupyTowarow', '=', 'GrupyTowarow.IDGrupyTowarow')
            ->where('Magazyn.IDMagazynu', $IDMagazynu)
            ->where(function ($query) use ($IDKontrahenta) {
                $query->where('RuchMagazynowy.IDKontrahenta', $IDKontrahenta)
                    ->orWhereNull($IDKontrahenta);
            })
            ->where('Magazyn.Hidden', 0)
            ->where(function ($query) use ($AllowDiscountDocs, $AllowZLDocs) {
                if ($AllowDiscountDocs == 0) {
                    $query->where('RuchMagazynowy.IDRodzajuRuchuMagazynowego', '<>', 8);
                }
                if ($AllowZLDocs == 0) {
                    $query->where('RuchMagazynowy.IDRodzajuRuchuMagazynowego', '<>', 27);
                }
            })
            ->groupBy('t.IDTowaru', 't.Nazwa', 't.KodKreskowy', 'GrupyTowarow.Nazwa', 'JednostkaMiary.Nazwa', 't.Uwagi')
            ->havingRaw('ISNULL(MAX(StanPoczatkowy.ilosc), 0) > 0 OR ISNULL(MAX(StanKoncowy.ilosc), 0) > 0 OR SUM(ABS(el.Ilosc * RuchMagazynowy.Operator)) > 0')
            ->get();
        return $results;
    }
}
