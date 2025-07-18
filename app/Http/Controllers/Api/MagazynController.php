<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\Collect;


class MagazynController extends Controller
{
    public function loadMagEmail()
    {
        return DB::select('SELECT [ID],[IDMagazynu] ,[Nazwa] ,[Symbol],koef, em.eMailAddress ,em.cod,em.IDLokalizaciiZwrot as Zwrot,em.IDKontrahenta, em.Zniszczony, em.Naprawa, em.noklient FROM [dbo].[Magazyn] RIGHT JOIN dbo.EMailMagazyn em ON em.IDMagazyn = IDMagazynu');
    }


    public function saveMagEmail(Request $request)
    {
        $data = $request->all();
        $IDMagazyn = $data['IDMagazynu'];
        if (isset($data['eMailAddress'])) {
            $eMailAddress = $data['eMailAddress'];
        } else {
            $eMailAddress = '';
        }
        if (isset($data['cod'])) {
            $cod = $data['cod'];
        } else {
            $cod = '';
        }
        if (isset($data['koef'])) {
            $koef = $data['koef'];
        } else {
            $koef = '';
        }
        $IDKontrahenta = $data['IDKontrahenta'];
        $IDLokalizaciiZwrot = isset($data['Zwrot']) ? $data['Zwrot'] : null;
        $Zniszczony = isset($data['Zniszczony']) ? $data['Zniszczony'] : null;
        $Naprawa = isset($data['Naprawa']) ? $data['Naprawa'] : null;
        $noklient = isset($data['noklient']) ? $data['noklient'] : [];

        if (isset($data['id'])) {
            $res = DB::table('dbo.EMailMagazyn')
                ->where('ID', (int) $data['id'])
                ->update([
                    'IDMagazyn' => $IDMagazyn,
                    'eMailAddress' => $eMailAddress,
                    'IDKontrahenta' => $IDKontrahenta,
                    'Naprawa' => $Naprawa,
                    'Zniszczony' => $Zniszczony,
                    'cod' => $cod,
                    'koef' => $koef,
                    'IDLokalizaciiZwrot' => $IDLokalizaciiZwrot,
                    'noklient' => json_encode($noklient)
                ]);
            if ($res) {
                return 0;
            } else {
                return response('Not updated', '404');
            }
        } else {
            $res =  DB::statement('INSERT INTO dbo.EMailMagazyn (IDMagazyn,eMailAddress,cod,koef,IDLokalizaciiZwrot,IDKontrahenta) VALUES (' . $IDMagazyn . ',\'' . $eMailAddress . '\',\'' . $cod . '\',\'' . $koef . '\',\'' . $IDLokalizaciiZwrot . '\',\'' . $IDKontrahenta . '\')');
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

    public function logUsers($user, $what, $ip = 0)
    {
        $myLogInfo = date('Y-m-d H:i:s') . ', ' . $ip . ', ' . $user->NazwaUzytkownika . ', ' . $what;
        file_put_contents(
            storage_path() . '/logs/useReport.log',
            $myLogInfo . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function getDataNotActivProduct(Request $request, $day, $idwarehouse)
    {
        $this->logUsers($request->user, 'NotActivProduct', $request->ip());

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
                    'Towar.Nazwa as Towar',
                    'Towar.KodKreskowy as Kod kreskowy',
                    'Towar._TowarTempString1 as sku',
                    //'jm.Nazwa as Jednostka',
                    DB::raw('CAST(a.SumaIlosci AS INT) as Stan'),
                    DB::raw("CASE WHEN Q.[Data przyjęcia towaru] = '1900-01-01' THEN NULL ELSE Q.[Data przyjęcia towaru] END as [Data przyjęcia towaru]"),
                    DB::raw("ISNULL(Q.[Numer dokumentu przyjęcia], '') as [Numer dokumentu przyjęcia]"),
                    DB::raw("CASE WHEN Q.[Data ostatniego wydania] = '1900-01-01' THEN NULL ELSE Q.[Data ostatniego wydania] END as [Data ostatniego wydania]"),
                    DB::raw("ISNULL(Q.[Numer ostatniego wydania], '') as [Numer ostatniego wydania]"),
                    //DB::raw("ISNULL(gt.Nazwa, 0) as [Grupa towarów]"),
                    //DB::raw('ROUND(Towar.CenaZakupu, 2) as [Cena zakupu]'),
                    DB::raw("CAST(DATEDIFF(DAY,ISNULL([Data ostatniego wydania],[Data przyjęcia towaru]),GETDATE()) as INT) AS [Ilość dni]"),
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
    private function getProductsInPrzyjęcie($idwarehouse, $date = null)
    {
        if (!$date) {
            $date = Carbon::now()->format('Y/m/d H:i:s');
        }
        $productsInLocation = [];
        $loc_names = DB::table('WarehouseLocations')
            ->where('IDMagazynu', $idwarehouse)
            ->where('TypLocations', 3)
            ->pluck('IDWarehouseLocation', 'LocationCode')
            ->toArray();

        foreach ($loc_names as $loc_name => $loc_id) {
            $param = 1; // 0 = Nazvanie, 1 = KodKreskowy
            $query = "SELECT dbo.StockInLocation(?, ?, ?) AS Stock";
            $result = DB::select($query, [$loc_id, $date, $param]);
            $resultString = $result[0]->Stock ?? null;
            $array = [];

            if ($resultString) {
                $pairs = explode(', ', $resultString);
                foreach ($pairs as $pair) {
                    list($key, $value) = explode(': ', $pair);
                    $array[$key] = (int) $value;
                }
                $productsInLocation[$loc_name] = $array;
            }
        }
        return $productsInLocation;
    }

    private function getProductsInLocation($idwarehouse, $date = null)
    {
        if (!$date) {
            $date = Carbon::now()->format('Y/m/d H:i:s');
        }
        $productsInLocation = [];
        $loc_names =  (array) DB::table('EMailMagazyn')
            ->where('IDMagazyn', $idwarehouse)
            ->select('Zniszczony', 'Naprawa')
            ->first();
        foreach ($loc_names as $loc_name => $loc_id) {
            $param = 1; // 0 = Nazvanie, 1 = KodKreskowy
            $query = "SELECT dbo.StockInLocation(?, ?, ?) AS Stock";
            $result = DB::select($query, [$loc_id, $date, $param]);
            $resultString = $result[0]->Stock ?? null;
            $array = [];

            if ($resultString) {
                $pairs = explode(', ', $resultString);
                foreach ($pairs as $pair) {
                    list($key, $value) = explode(': ', $pair);
                    $array[$key] = (int) $value;
                }
                $productsInLocation[$loc_name] = $array;
            }
        }
        return $productsInLocation;
    }

    public function getDataForXLSDay(Request $request, $day, $idwarehouse)
    {
        $this->logUsers($request->user, 'Day', $request->ip());
        if ($this->canUseWarehouse($request->user, $idwarehouse)) {
            $res = [];
            //$date = Carbon::now()->parse($day)->setTime(23, 59, 59)->format('d/m/Y H:i:s');
            // $date = Carbon::now()->parse($day)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            $date = Carbon::parse($day)->setTime(23, 59, 59)->format('m.d.Y H:i:s');

            $productsInLocation = $this->getProductsInLocation($idwarehouse, $date);
            $productsInPrzyjęcie = $this->getProductsInPrzyjęcie($idwarehouse, $date);
            $products = $this->getWarehouseData($date, $idwarehouse);
            foreach ($products as $product) {
                $kodKreskowy = $product->KodKreskowy;

                foreach ($productsInLocation as $loc_name => $locationData) {
                    // Check if the product's KodKreskowy exists in the location data
                    if (isset($locationData[$kodKreskowy])) {
                        // Add the loc_name column to the product
                        $product->$loc_name = $locationData[$kodKreskowy];

                        $product->pozostać -= $locationData[$kodKreskowy];
                    } else {
                        // If KodKreskowy does not exist in the location data, set the column to null or 0
                        $product->$loc_name = null; // or 0, depending on your requirements
                    }
                }
                $product->przyjęcie = 0; // Initialize przyjęcie to 0
                foreach ($productsInPrzyjęcie as $loc_namep => $locationData) {
                    // Check if the product's KodKreskowy exists in the location data
                    if (isset($locationData[$kodKreskowy])) {
                        // Add the loc_name column to the product
                        $product->$loc_namep = $locationData[$kodKreskowy];
                        $product->przyjęcie += $locationData[$kodKreskowy]; // Initialize dostawa to 0
                        $product->pozostać -= $locationData[$kodKreskowy];
                    } else {
                        // If KodKreskowy does not exist in the location data, set the column to null or 0
                        $product->$loc_namep = null; // or 0, depending on your requirements
                    }
                }
            }

            $res[Carbon::parse($day)->format('d-m-Y')] = $products;
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
                DB::raw('t.Nazwa as "Nazwa"'),
                't.KodKreskowy',
                't._TowarTempString2 as AnalizABC',
                'GrupyTowarow.Nazwa as GrupaTowarów',
                DB::raw('t._TowarTempString1 as sku'),
                DB::raw('CAST(SUM(DostawyMinusWydania.Wartosc) as decimal(32,2)) as wartosc'),
                DB::raw('CAST(ISNULL(SUM(DostawyMinusWydania.ilosc) * t._TowarTempDecimal2, 0) as decimal(32,8)) as m3xstan'),
                DB::raw('CAST(SUM(DostawyMinusWydania.ilosc) AS INT) as stan'),
                DB::raw('CAST(ISNULL(ol.ProductCountWithoutWZ, 0) AS INT) as rezerv'),
                DB::raw('CAST(ISNULL(SUM(DostawyMinusWydania.ilosc) - ISNULL(ol.ProductCountWithoutWZ, 0), 0) AS INT) as pozostać')
            ])
            ->joinSub(function ($query) use ($dataMax, $idMagazynu) {
                $query->from('ElementRuchuMagazynowego as PZ')
                    ->select([
                        't.IdTowaru',
                        DB::raw('SUM(PZ.ilosc) as Ilosc'),
                        DB::raw('CAST(SUM(ISNULL(PZ.ilosc * PZ.CenaJednostkowa, 0)) as decimal(32,2)) as Wartosc'),
                        DB::raw('CAST(SUM(ISNULL(PZ.ilosc * PZ.CenaJednostkowa, 0)) as decimal(32,2)) as Bilans')
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
            ->leftJoin('GrupyTowarow', 'GrupyTowarow.IDGrupyTowarow', '=', 't.IDGrupyTowarow')
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
                't._TowarTempString2',
                'GrupyTowarow.Nazwa',
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
        $this->logUsers($request->user, 'Tarif', $request->ip());
        $now = Carbon::now();
        if ($this->canUseWarehouse($request->user, $idwarehouse)) {
            $year = $now->year;
            if ($now->month == 1 && $month == 11) {
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
        // Получаем IDMagazynu для указанного товара
        $IDMagazynu = DB::table('Towar')
            ->where('IDTowaru', $IDTowaru)
            ->value('IDMagazynu');

        // Инициализируем StanMagazynu
        $StanMagazynu = 0;

        // Получаем последнюю дату начала инвентаризации
        $BODate = DB::select('SELECT dbo.MostRecentOBDateScalar(?, ?) as BODate', [now(), $IDMagazynu])[0]->BODate;

        // Основной запрос для получения истории товара
        $results = DB::select('
            WITH CTE AS (
            SELECT
            ROW_NUMBER() OVER(ORDER BY Data ASC) as ID,
            r.IDRuchuMagazynowego,
            CASE
            WHEN pmm.IDPrzesunieciaMM IS NULL THEN r.Data
            ELSE DATEADD(ms, 333, r.Data)
            END AS Data,
            r.Uwagi,
            r.IDRodzajuRuchuMagazynowego,
            r.IDMagazynu,
            r.NrDokumentu,
            r.IDKontrahenta,
            Kontrahent.Nazwa AS NazwaKontrahenta,
            RodzajRuchuMagazynowego.Nazwa AS NazwaRuchu,
            CAST(r.Operator * (
            SELECT SUM(e_X.Ilosc)
            FROM ElementRuchuMagazynowego e_X
            WHERE e_X.IDTowaru = ?
            AND e_X.IDRuchuMagazynowego = r.IDRuchuMagazynowego
            ) as decimal(32,2)) AS ilosc,
            CAST(0 AS decimal(18,7)) AS StanMagazynu,
            (
            SELECT
                CASE
                WHEN SUM(Ilosc) = 0 THEN 0
                ELSE SUM(CenaJednostkowa * Ilosc) / SUM(Ilosc)
                END
            FROM ElementRuchuMagazynowego
            WHERE IDTowaru = ?
            AND IDRuchuMagazynowego = r.IDRuchuMagazynowego
            ) AS CenaJednostkowa
            FROM RuchMagazynowy r
            LEFT JOIN Kontrahent ON r.IDKontrahenta = Kontrahent.IDKontrahenta
            INNER JOIN RodzajRuchuMagazynowego ON r.IDRodzajuRuchuMagazynowego = RodzajRuchuMagazynowego.IDRodzajuRuchuMagazynowego
            LEFT JOIN dbo.PrzesunieciaMM pmm ON pmm.IDRuchuMagazynowegoDo = r.IDRuchuMagazynowego
            WHERE EXISTS (
            SELECT 1
            FROM ElementRuchuMagazynowego
            WHERE IDTowaru = ?
            AND IDRuchuMagazynowego = r.IDRuchuMagazynowego
            )
            AND r.Data >= ?
            AND r.Operator <> 0
            AND r.IDRodzajuRuchuMagazynowego <> 27
            )
            SELECT *, ISNULL(CAST(SUM(ilosc) OVER (ORDER BY Data ASC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) as decimal(32,2)),0) AS StanMagazynu
            FROM CTE
            ORDER BY Data DESC
        ', [$IDTowaru, $IDTowaru, $IDTowaru, $BODate]);

        return $results;
    }

    public function getOborot(Request $request)
    {
        if (isset($request->user)) {
            $this->logUsers($request->user, 'Oborot', $request->ip());
        }

        $data = $request->all();
        $allClients = $data['allClients'];
        $c_dataMin = new Carbon($data['dataMin']);

        $dataMin = $c_dataMin->format('Y-m-d H:i:s');
        // $dataMin = $c_dataMin->format('Y/d/m H:i:s');
        // $dataMin = $c_dataMin->format('d.m.Y H:i:s');
        $c_dataMax = new Carbon($data['dataMax']);
        $dataMax = $c_dataMax->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        // $dataMax = $c_dataMax->setTime(23, 59, 59)->format('Y/d/m H:i:s');
        // $dataMax = $c_dataMax->setTime(23, 59, 59)->format('d.m.Y H:i:s');
        $IDMagazynu = $data['IDMagazynu'];

        $noklient = $this->dontGetDocKlient($IDMagazynu);

        $AllowDiscountDocs = 0;
        $AllowZLDocs = 0;
        $AllItems = 1;

        $results = DB::table('Towar as t')
            ->select([
                't.IDTowaru',
                't.Nazwa as Towar',
                't.Uwagi',
                't.KodKreskowy as KodKreskowy',
                't._TowarTempString2 as AnalizABC',
                // 'GrupyTowarow.Nazwa as GrupaTowarów',
                DB::raw('t._TowarTempString1 as sku'),
                DB::raw('CAST(ISNULL(MAX(StanPoczatkowy.ilosc), 0) as INT) as StanPoczatkowy'),
                DB::raw('CAST(ISNULL(MAX(StanPoczatkowy.wartosc), 0) as decimal(32,2)) as WartośćPoczątkowa'),
                DB::raw('CAST(ISNULL(SUM(el.Ilosc * CASE WHEN RuchMagazynowy.Operator * el.Ilosc > 0 THEN 1 ELSE 0 END), 0) as INT) as IlośćWchodząca'),
                DB::raw('CAST(ISNULL(SUM(el.Ilosc * CASE WHEN RuchMagazynowy.Operator * el.Ilosc > 0 THEN 1 ELSE 0 END * el.CenaJednostkowa), 0) as decimal(32,2)) as WartośćWchodząca'),
                DB::raw('CAST(ISNULL(SUM(ABS(el.Ilosc) * CASE WHEN RuchMagazynowy.Operator * el.Ilosc < 0 THEN 1 ELSE 0 END), 0) as INT) as IlośćWychodząca'),
                DB::raw('CAST(ISNULL(SUM(ABS(el.Ilosc) * CASE WHEN RuchMagazynowy.Operator * el.Ilosc < 0 THEN 1 ELSE 0 END * el.CenaJednostkowa), 0) as decimal(32,2)) as WartośćWychodząca'),
                DB::raw('CAST(ISNULL(MAX(StanKoncowy.ilosc), 0) as INT) as StanKoncowy'),
                DB::raw('CAST(ISNULL(MAX(StanKoncowy.wartosc), 0) as decimal(32,2)) as WartośćKoncowa'),
            ])
            ->join('JednostkaMiary', 'JednostkaMiary.IDJednostkiMiary', '=', 't.IDJednostkiMiary')
            ->join('Magazyn', 't.IDMagazynu', '=', 'Magazyn.IDMagazynu')
            ->join(DB::raw("dbo.MostRecentOBDate('$dataMax') as BO"), 't.IDMagazynu', '=', 'BO.IDMagazynu')
            ->leftJoin(DB::raw("dbo.StanyWDniu('$dataMin') as StanPoczatkowy"), 'StanPoczatkowy.IDTowaru', '=', 't.IDTowaru')
            ->leftJoin(DB::raw("dbo.StanyWDniu('$dataMax') as StanKoncowy"), 'StanKoncowy.IDTowaru', '=', 't.IDTowaru')
            ->leftJoin('ElementRuchuMagazynowego as el', 'el.IDTowaru', '=', 't.IDTowaru')
            ->leftJoin('RuchMagazynowy', function ($join) use ($dataMin, $dataMax) {
                $join->on('el.IDRuchuMagazynowego', '=', 'RuchMagazynowy.IDRuchuMagazynowego')
                    ->whereBetween('RuchMagazynowy.Data', [$dataMin, $dataMax]);
            })
            ->leftJoin('GrupyTowarow', 't.IDGrupyTowarow', '=', 'GrupyTowarow.IDGrupyTowarow')
            ->leftJoin('Kontrahent', 'Kontrahent.IDKontrahenta', '=', 'RuchMagazynowy.IDKontrahenta')

            ->where('Magazyn.IDMagazynu', $IDMagazynu)
            ->where('Magazyn.Hidden', 0)
            ->when(!$allClients && is_array($noklient) && !empty($noklient), function ($query) use ($noklient) {
                return $query->whereNotIn('RuchMagazynowy.IDKontrahenta', $noklient);
            })
            // ->when($IDGrupyTowarow, function ($query, $IDGrupyTowarow) {
            //     return $query->where('t.IDGrupyTowarow', $IDGrupyTowarow);
            // })
            ->when(!$AllowDiscountDocs, function ($query) {
                return $query->where('RuchMagazynowy.IDRodzajuRuchuMagazynowego', '<>', 8);
            })
            ->when(!$AllowZLDocs, function ($query) {
                return $query->where('RuchMagazynowy.IDRodzajuRuchuMagazynowego', '<>', 27);
            })
            ->groupBy('t.IDTowaru', 't.Nazwa', 't.KodKreskowy', 't.Uwagi', 't._TowarTempString1', 't._TowarTempString2')
            ->havingRaw($AllItems ? '1=1' : 'ISNULL(MAX(StanPoczatkowy.ilosc), 0) > 0 OR ISNULL(MAX(StanKoncowy.ilosc), 0) > 0 OR SUM(ABS(el.Ilosc * RuchMagazynowy.Operator)) > 0')
            ->orderBy('t.IDTowaru', 'ASC')
            ->get();


        // $sql_with_bindings = Str::replaceArray('?', $results->getBindings(), $results->toSql());
        // dd($sql_with_bindings);
        return $results;
    }

    private function dontGetDocKlient($IDMagazynu)
    {
        $noklient = DB::table('EMailMagazyn')->where('IDMagazyn', $IDMagazynu)->whereNotNull('noklient')->whereRaw("LEN(CAST(noklient AS VARCHAR(MAX))) > 5")->value('noklient');
        $noklient = json_decode($noklient, true);

        if (is_null($noklient) or (is_array($noklient) && count($noklient) == 0)) {
            $noklient = [];
        }

        return $noklient;
    }

    public function  getQuantity(Request $request)
    {
        $data = $request->all();
        $res = [];
        if (isset($request->user)) {
            $this->logUsers($request->user, 'Zamawianie towarów', $request->ip());
        }

        $datecur = new Carbon();

        $dateDoMin = new Carbon($data['dataDoMin']);
        $dateDoMinF = $dateDoMin->format('Y-m-d');

        $dateDoMax = new Carbon($data['dataDoMax']);
        $dateDoMaxF = $dateDoMax->format('Y-m-d');

        $dateMin = new Carbon($data['dataMin']);
        $dateMinF = $dateMin->format('Y-m-d');

        $dateMax = new Carbon($data['dataMax']);
        $dateMaxF = $dateMax->format('Y-m-d');

        $DaysOn = $data['DaysOn'];

        $IDMagazynu = $data['IDMagazynu'];

        $noklient = $this->dontGetDocKlient($IDMagazynu);
        $res['kontahenty'] = implode(',', DB::table('Kontrahent')->whereIn('IDKontrahenta', $noklient)->pluck('Nazwa')->toArray());

        $a_products = [];
        for ($date = $dateDoMin->copy(); $date->lte($dateDoMax); $date->addDay()) {

            // $products = $this->getWarehouseData($date->setTime(23, 59, 59)->format('d.m.Y H:i:s'), $IDMagazynu);
            $products = $this->getWarehouseData($date->setTime(00, 00, 00)->format('Y-m-d H:i:s'), $IDMagazynu);

            $productsInLocation = $this->getProductsInLocation($IDMagazynu, $date);
            foreach ($products as $product) {
                foreach ($productsInLocation as $loc_name => $locationData) {
                    if (isset($locationData[$product->KodKreskowy])) {
                        $product->stan -= $locationData[$product->KodKreskowy];
                    }
                }
                if ($product->stan > 0) {
                    if (isset($a_products[$product->IDTowaru])) {
                        $a_products[$product->IDTowaru]['qtyOld']++;
                    } else {
                        $a_products[$product->IDTowaru] = [
                            "Nazwa" => $product->Nazwa,
                            'KodKreskowy' => $product->KodKreskowy,
                            'sku' => $product->sku,
                            'GrupaTowarów' => $product->GrupaTowarów,
                            'qtyOld' => 1,
                            'qtyNew' => 0,
                            'oborotOld' => 0,
                            'oborotNew' => 0,
                            'stan' => 0,
                            'AnalizABC' => $product->AnalizABC,
                        ];
                    }
                }
            }
        }
        for ($date = $dateMin->copy(); $date->lte($dateMax); $date->addDay()) {

            // $products = $this->getWarehouseData($date->setTime(23, 59, 59)->format('d.m.Y H:i:s'), $IDMagazynu);
            $products = $this->getWarehouseData($date->setTime(00, 00, 00)->format('Y-m-d H:i:s'), $IDMagazynu);
            $productsInLocation = $this->getProductsInLocation($IDMagazynu, $date);
            foreach ($products as $product) {

                foreach ($productsInLocation as $loc_name => $locationData) {
                    if (isset($locationData[$product->KodKreskowy])) {
                        $product->stan -= $locationData[$product->KodKreskowy];
                    }
                }
                if ($product->stan > 0) {
                    if (isset($a_products[$product->IDTowaru])) {
                        $a_products[$product->IDTowaru]['qtyNew']++;
                    } else {
                        $a_products[$product->IDTowaru] = [
                            "Nazwa" => $product->Nazwa,
                            'KodKreskowy' => $product->KodKreskowy,
                            'sku' => $product->sku,
                            'GrupaTowarów' => $product->GrupaTowarów,
                            'qtyOld' => 0,
                            'qtyNew' => 1,
                            'oborotOld' => 0,
                            'oborotNew' => 0,
                            'stan' => 0,
                            'AnalizABC' => $product->AnalizABC,
                        ];
                    }
                }
            }
        }

        $products = $this->getWarehouseData($datecur->setTime(23, 59, 59)->format('Y-m-d H:i:s'), $IDMagazynu);
        foreach ($products as $product) {
            if (isset($a_products[$product->IDTowaru])) {
                $a_products[$product->IDTowaru]['stan'] = $product->stan;
            }
        }

        $request = new \Illuminate\Http\Request();
        $request->replace(['dataMin' => $dateDoMinF, 'dataMax' => $dateDoMaxF, 'IDMagazynu' => $IDMagazynu, 'allClients' => 0]);
        $products = $this->getOborot($request);
        foreach ($products as $product) {
            if (isset($a_products[$product->IDTowaru])) {
                $a_products[$product->IDTowaru]['oborotOld'] = $product->IlośćWychodząca;
            }
        }
        $request = new \Illuminate\Http\Request();
        $request->replace(['dataMin' => $dateMinF, 'dataMax' => $dateMaxF, 'IDMagazynu' => $IDMagazynu, 'allClients' => 0]);
        $products = $this->getOborot($request);
        foreach ($products as $product) {
            if (isset($a_products[$product->IDTowaru])) {
                $a_products[$product->IDTowaru]['oborotNew'] = $product->IlośćWychodząca;
            }
        }
        $res['products'] = [];
        // product in 'Zniszczony', 'Naprawa'
        $productsInLocation = $this->getProductsInLocation($IDMagazynu, $dateMaxF);
        foreach ($a_products as $IDTowaru => $product) {

            foreach ($productsInLocation as $loc_name => $locationData) {
                $product[$loc_name] = 0;
                if (isset($locationData[$product['KodKreskowy']])) {
                    $product['stan'] -= $locationData[$product['KodKreskowy']];
                    $product[$loc_name] = $locationData[$product['KodKreskowy']];
                }
            }

            $selondayOld = $product['qtyOld'] > 0 ? round($product['oborotOld'] / $product['qtyOld'], 2) : 0;
            $selonday = $product['qtyNew'] > 0 ? round($product['oborotNew'] / $product['qtyNew'], 2) : 0;
            $tendent = $selondayOld > 0 && $selonday > 0 ? round(($selonday - $selondayOld) / $selondayOld * 100, 2) : 0;
            $zamov = $tendent > 0 ? round(($DaysOn * $selonday - $product['stan']) + ($DaysOn * $selonday - $product['stan']) /  $tendent, 2) : round($DaysOn * $selonday - $product['stan'], 2);
            $haveDay = $selonday > 0 ? $product['stan'] / $selonday : 0;
            $res['products'][] = [
                'IDTowaru' => $IDTowaru,
                'Nazwa' => $product['Nazwa'],
                'KodKreskowy' => (int)$product['KodKreskowy'],
                'Zniszczony' => $product['Zniszczony'],
                'Naprawa' => $product['Naprawa'],
                'SKU' => $product['sku'],
                'AnalizABC' => $product['AnalizABC'],
                'GrupaTowarów' => $product['GrupaTowarów'],
                'stan' => (int)$product['stan'],
                'DniNaDostawę' => (int)$DaysOn,
                'Trend' => round(
                    $tendent,
                    2
                ),

                'SprzedażWdniuMagazynowania' => round(
                    $selonday,
                    2
                ),
                'Zamówienie' => $zamov,
                'DniDoKońca' => (int)$haveDay,

                'DaysInStockOkresOld' => (int) $product['qtyOld'],
                'DaysInStockOkresNew' => (int) $product['qtyNew'],
                'ObrótOkresOld' => (int) $product['oborotOld'],
                'ObrótOkresNew' => round(
                    $product['oborotNew'],
                    2
                ),

            ];
        }

        return $res;
    }

    public function getOrders(Request $request)
    {

        $res = [];
        $data = $request->all();
        $IDWarehouse = $data['IDWarehouse'];
        $dateMin = new Carbon($data['dateMin']);
        $dateMax = new Carbon($data['dateMax']);
        $dateMin = $dateMin->setTime(00, 00, 00)->format('Y-m-d H:i:s');
        $dateMax = $dateMax->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        // $empty = $data['empty'];
        // $full = $data['full'];

        $query = DB::table('Orders as ord')
            ->select(
                'IDOrder',
                '_OrdersTempString5 as product_Chang',
                'rm.NrDokumentu as Powiązane_WZ',
                'rm.Data as DataWZ',
                'Number',
                'Date as DataZO',
                'con.Nazwa as Kontrahent',

                'os.Name as Status',
                'ord.Remarks as Uwagi',
                'ord.Modified as Zmodyfikowane',
                'rt.Nazwa as Rodzaj_transportu',
                DB::raw('CAST(_OrdersTempDecimal2 AS INT) as Nr_Baselinker'),
                '_OrdersTempString2 as Nr_Nadania',
                '_OrdersTempString1 as Nr_Faktury',
                '_OrdersTempString4 as Nr_Zwrotny',
                '_OrdersTempString3 as Nr_Korekty',
                '_OrdersTempString7 as Źródło',
                '_OrdersTempString8 as External_id',
                '_OrdersTempString9 as Login_klienta',
                DB::raw('(SELECT CAST(SUM(ol.PriceGross * ol.Quantity) AS DECIMAL(10,2)) FROM OrderLines ol WHERE ol.IDOrder = ord.IDOrder) as KwotaBrutto')
            )
            ->leftJoin('OrderStatus as os', 'os.IDOrderStatus', 'ord.IDOrderStatus')
            ->leftJoin('Kontrahent as con', 'con.IDKontrahenta', 'ord.IDAccount')
            ->leftJoin('DocumentRelations as do', function ($join) {
                $join->on('do.ID2', '=', 'ord.IDOrder')
                    ->on('do.IDType1', '=', DB::raw('2'));
            })
            ->leftJoin('RuchMagazynowy as rm', 'rm.IDRuchuMagazynowego', 'do.ID1')
            ->leftJoin('RodzajTransportu as rt', 'rt.IDRodzajuTransportu', 'ord.IDTransport')
            ->where('ord.IDWarehouse', $IDWarehouse)
            ->whereBetween('Date', [$dateMin, $dateMax])
            ->orderBy('Date', 'desc');

        $res['orders'] = $query->get();
        return $res;
    }

    public function lastNumber($doc, $symbol)
    {
        $year = Carbon::now()->format('y');
        $pattern =  $doc . '%/' . $year . ' - ' . $symbol;
        $patternIndex = strlen($doc);
        $patternToEndLen = strlen($symbol) + 6; // 6 символов: " - " + год (2 символа) + "/"

        $res = DB::table('RuchMagazynowy')
            ->select(DB::raw('MAX(CAST(SUBSTRING(NrDokumentu, ' . ($patternIndex + 1) . ', LEN(NrDokumentu) - ' . ($patternToEndLen + $patternIndex) . ') AS INT)) as max_number'))
            ->whereRaw('RTRIM(NrDokumentu) LIKE ?', [$pattern])
            ->whereRaw('ISNUMERIC(SUBSTRING(NrDokumentu, ' . ($patternIndex + 1) . ', LEN(NrDokumentu) - ' . ($patternToEndLen + $patternIndex) . ')) <> 0')
            ->value('max_number');

        if ($res === null) {
            return str_replace('%', '1', $pattern);
        }
        return str_replace('%', $res + 1, $pattern);
    }

    private function isInCollect($orderId)
    {
        $collect = Collect::query()->where('IDOrder', $orderId)->where('status', 0)->first();
        if ($collect) {
            return true;
        }
        return false;
    }


    private function createWZfromCollect($orderId, $UserID)
    {
        $OrderType = 15;
        $DocumentType = 2;
        $DocDate = Carbon::now();
        $InvAlgGross = DB::table('Ustawienia')->where('Nazwa', 'InvoiceAlgorithm')->value('Wartosc') == 'Brutto' ? 1 : 0;
        $PricesModeGross = DB::table('Ustawienia')->where('Nazwa', 'PricesMode')->value('Wartosc') == 'Brutto' ? 1 : 0;
        $collect = Collect::query()->where('IDOrder', $orderId)->where('status', 0)->first();
        $order = DB::table('Orders')->where('IDOrder', $orderId)->first();
        $symbol = DB::table('Magazyn')->where('IDMagazynu', $order->IDWarehouse)->value('Symbol');
        $documentNumber = $this->lastNumber('WZ', $symbol);
        $DocumentID = 0;
        $o_collectIDsElements = is_array($collect->IDsElementuRuchuMagazynowego)
            ? $collect->IDsElementuRuchuMagazynowego
            : json_decode($collect->IDsElementuRuchuMagazynowego);

        // get order products
        $products = DB::table('OrderLines as ol')->join('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')->where('t.Usluga', 0)->where('IDOrder', $orderId)->get();

        try {
            DB::beginTransaction();
            // DB::transaction(function () use ($o_collectIDsElements, $products, $orderId, $UserID, $DocumentType, $DocDate, $documentNumber, $InvAlgGross, $PricesModeGross, $order, $OrderType, $DocumentID) {
            $stat =  DB::table('dbo.RuchMagazynowy')->insert([
                'Data' => $DocDate,
                'Uwagi' => 'User' . $UserID . '|| ' . $order->Remarks,
                'IDRodzajuRuchuMagazynowego' => $DocumentType,
                'IDMagazynu' => $order->IDWarehouse,
                'NrDokumentu' => $documentNumber,
                'IDKontrahenta' => $order->IDAccount,
                'IDUzytkownika' => $UserID,
                'IDGrupyKosztow' => $order->IDCostType,
                'IDRodzajuTransportu' => $order->IDTransport,
                'TransportNumber' => $order->TransportNumber,
                'IDPaymentType' => $order->IDPaymentType,
                'IDCompany' => $order->IDCompany,
                'IDCurrency' => $order->IDCurrency,
                'IDCurrencyRate' => $order->IDCurrencyRate,
                '_RuchMagazynowyTempDecimal1' => $order->_OrdersTempDecimal2, //N baseLinker
                '_RuchMagazynowyTempString2'  => $order->_OrdersTempString1, // N Factury BL
                '_RuchMagazynowyTempString1'  => $order->_OrdersTempString2, // N nadania BL
                '_RuchMagazynowyTempString5'  => $order->_OrdersTempString5, //Product_chang
                '_RuchMagazynowyTempString7'  => $order->_OrdersTempString7, //Zrodlo
                '_RuchMagazynowyTempString8'  => $order->_OrdersTempString8, // External_id
                '_RuchMagazynowyTempString9'  => $order->_OrdersTempString9, //Login_klienta
            ]);

            $DocumentID = DB::table('dbo.RuchMagazynowy')->orderBy('IDRuchuMagazynowego', 'desc')->take(1)->value('IDRuchuMagazynowego');
            // Log::info('RuchMagazynowy: ' . $stat . ' DocumentID: ' . $DocumentID);
            if (!$stat) {
                Log::error('Error creating RuchMagazynowy for WZ order ID' . $orderId);
                throw new \Exception('Error creating RuchMagazynowy for WZ order ID' . $orderId);
            }
            foreach ($o_collectIDsElements as $IDItem => $IDElement) {
                $product = $products->filter(function ($product) use ($IDItem) {
                    return $product->IDItem == $IDItem;
                })->first();
                foreach ($IDElement['pls'] as  $ZL_IDElementuRuchuMagazynowego) {
                    $Element = DB::table('ElementRuchuMagazynowego')->where('IDElementuRuchuMagazynowego', $ZL_IDElementuRuchuMagazynowego)->first();
                    $PriceNet = $product->PriceNet;
                    $PriceGross = $product->PriceGross;
                    $IDVat = $product->IDVat;
                    $Remarks = $product->Remarks;
                    $Discount = $product->Discount;
                    $FCPriceGross = $product->ForeignCurrencyPriceGross;
                    $FCPriceNet = $product->ForeignCurrencyPriceNet;

                    //$OnStock = DB::table('stanywdniu')->where('IDTowaru', $IDItem)->value('ilosc') ?? 0;

                    $VatRate = DB::table('dbo.VatRates')->where('IDVatRate', $IDVat)->value('Rate');
                    $CurrencyRateBaseToForeign = 1;
                    if ($FCPriceGross > 0 || $FCPriceNet > 0) {
                        $CurrencyRateBaseToForeign = DB::table('dbo.Orders')->where('IDOrder', $orderId)->value(DB::raw('dbo.CalculateRate(IDCurrency, IDCurrencyRate, 0)'));
                    }

                    if (DB::table('Ustawienia')->where('Nazwa', 'UseDiscounts')->value('Wartosc') == 'Nie') {
                        $Discount = 0;
                    }
                    if ($FCPriceGross > 0 || $FCPriceNet > 0) {
                        $Discount = $Discount * DB::table('dbo.Orders')->where('IDOrder', $orderId)->value(DB::raw('dbo.CalculateRate(IDCurrency, IDCurrencyRate, 1)'));
                    }

                    if ($InvAlgGross != $PricesModeGross && $VatRate !== null) {
                        if ($InvAlgGross == 1 && $PricesModeGross == 0) {
                            $PriceNet = $PriceGross / (1 + $VatRate / 100) + $Discount;
                        } else {
                            $PriceGross = $PriceNet * (1 + $VatRate / 100) - $Discount;
                        }
                    }

                    if (DB::table('Ustawienia')->where('Nazwa', 'PricesMode')->value('Wartosc') == 'Brutto') {
                        if ($PriceGross != 0) {
                            $Price = $PriceGross;
                        } else {
                            $Price = ($PriceNet - $Discount) * (1 + $VatRate / 100);
                        }
                    } else {
                        if ($PriceNet != 0) {
                            $Price = ($PriceNet - $Discount);
                        } else {
                            $Price = $PriceGross / (1 + $VatRate / 100);
                        }
                    }

                    if (($FCPriceGross > 0 || $FCPriceNet > 0) && DB::table('Ustawienia')->where('Nazwa', 'UseCurrenciesOnPZ')->value('Wartosc') == 1) {
                        $FCPrice = $Price * DB::table('dbo.Orders')->where('IDOrder', $orderId)->value(DB::raw('dbo.CalculateRate(IDCurrency, IDCurrencyRate, 0)'));
                    }

                    $stat = DB::table('dbo.ElementRuchuMagazynowego')->insert([
                        'Ilosc' => $Element->Ilosc,
                        'Uwagi' => 'User' . $UserID . '|| ' . $product->Remarks,
                        'CenaJednostkowa' => $Element->CenaJednostkowa,
                        'IDRuchuMagazynowego' => $DocumentID,
                        'IDTowaru' => $IDItem,
                        'Uzytkownik' => $UserID,
                        'IDOrderLine' => $product->IDOrderLine,
                        'CurrencyPrice' => $FCPrice ?? null,
                    ]);

                    $ElementID = DB::table('dbo.ElementRuchuMagazynowego')->orderBy('IDElementuRuchuMagazynowego', 'desc')->take(1)->value('IDElementuRuchuMagazynowego');
                    // Log::info('ElementRuchuMagazynowego: ' . $stat);
                    if (!$stat) {
                        Log::error('Error creating ElementRuchuMagazynowego for WZ order ID' . $orderId . ' IDRuchuMagazynowego' . $DocumentID);
                        throw new \Exception('Error creating ElementRuchuMagazynowego for WZ order ID' . $orderId . ' IDRuchuMagazynowego' . $DocumentID);
                    }

                    //Log::info('UtworzZaleznoscPZWZ: ' . $ZL_IDElementuRuchuMagazynowego . ' ' . $ElementID . ' ' . $product->Quantity);
                    // Check if both elements have the same IDTowaru before creating the relation
                    $relatedElement = DB::table('ElementRuchuMagazynowego')->where('IDElementuRuchuMagazynowego', $ZL_IDElementuRuchuMagazynowego)->first();
                    if ($relatedElement && $relatedElement->IDTowaru == $IDItem) {
                        DB::statement('EXEC [dbo].[UtworzZaleznoscPZWZ] ?, ?, ?', [$ZL_IDElementuRuchuMagazynowego, $ElementID, $Element->Ilosc]);
                    } else {
                        Log::warning('Skipped UtworzZaleznoscPZWZ: Items have different IDTowaru', [
                            'ZL_IDElementuRuchuMagazynowego' => $ZL_IDElementuRuchuMagazynowego,
                            'ElementID' => $ElementID,
                            'ElementIlosc' => $Element->Ilosc,
                            'related_IDTowaru' => $relatedElement->IDTowaru ?? null,
                            'current_IDTowaru' => $IDItem
                        ]);
                    }
                    $forlog = DB::table('RuchMagazynowy')->where('IDRuchuMagazynowego', $DocumentID)->value('IDRuchuMagazynowego');
                    if ($forlog == null) {
                        Log::error('Error creating UtworzZaleznoscPZWZ for WZ order ID' . $orderId . ' IDRuchuMagazynowego' . $DocumentID);
                        throw new \Exception('Error creating UtworzZaleznoscPZWZ for WZ order ID' . $orderId . ' IDRuchuMagazynowego' . $DocumentID);
                    }
                }
            }

            $stat = DB::table('dbo.DocumentRelations')->insert([
                'ID1' => $DocumentID,
                'IDType1' => $DocumentType,
                'ID2' => $orderId,
                'IDType2' => $OrderType,
            ]);
            if (!$stat) {
                throw new \Exception('Error creating DocumentRelations for WZ order ID2' . $orderId);
            }
            Collect::query()->where('IDOrder', $orderId)->update(['status' => 1]);
            DB::commit();
            // });
        } catch (\Exception $e) {
            Log::error('Error creating WZ from collect: ' . $e->getMessage(), ['exception' => $e]);
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
        return ['id' => $DocumentID, 'Powiązane_WZ' => $documentNumber, 'date' =>  $DocDate->format('Y-m-d H:i:s')];
    }


    public function createWZfromZO(Request $request)
    {

        $data = $request->all();
        $listOrders = [];
        $IDOrders = $data['IDOrders'];
        $symbol = $data['warehouse'][0]['Symbol'];
        $documentType = 2;
        $userId = 1;
        $amountFlag = 2;
        $elementsGUID = NULL;

        foreach ($IDOrders as $order) {
            $orderId = $order['IDOrder'];
            $relatedDocument = DB::table('DocumentRelations')->where('ID2', $orderId)->where('IDType2', 15)->where('IDType1', 2)->value('ID1');


            if (!$relatedDocument) {
                // check if order is in collection
                if ($this->isInCollect($orderId)) {
                    $relatedDocument = $this->createWZfromCollect($orderId, $request->user->IDUzytkownika);
                    if ($relatedDocument == null) {
                        return response('Error create doc WZ', 500);
                    }
                } else {
                    $documentNumber = $this->lastNumber('WZ', $symbol);
                    DB::statement(' DECLARE @DocumentID INT; EXEC GenerateWZfromOrder @DocumentType = :documentType, @UserID = :userId, @AmountFlag = :amountFlag, @ElementsGUID = :elementsGUID, @OrderID = :orderId, @DocumentNumber = :documentNumber, @DocumentID = @DocumentID OUTPUT; ', ['documentType' => $documentType, 'userId' => $userId, 'amountFlag' => $amountFlag, 'elementsGUID' => $elementsGUID, 'orderId' => $orderId, 'documentNumber' => $documentNumber]);

                    $relatedDocument = DB::table('DocumentRelations')
                        ->leftJoin('RuchMagazynowy', 'DocumentRelations.ID1', 'RuchMagazynowy.IDRuchuMagazynowego')
                        ->where('ID2', $orderId)
                        ->where('IDType2', 15)
                        ->where('IDType1', 2)
                        ->select('ID1 as id', 'NrDokumentu as Powiązane_WZ', 'Data as date')
                        ->first();
                }
                $listOrders[$orderId] = $relatedDocument;
            } else {
                return response('Order ID ' . $orderId . ' already has WZ ', 500);
            }
        }
        return $listOrders;
    }

    public function getStatuses()
    {
        return DB::table('OrderStatus')->select('IDOrderStatus as value', 'Name as title')->get();
    }

    public function getClients()
    {
        return DB::table('Kontrahent')->select('IDKontrahenta as value', 'Nazwa as title')->whereRaw('LEN(Nazwa) >= 3')->get();
    }

    public function setCenaWZkFromWZ()
    {

        $o_prices = DB::table('ElementRuchuMagazynowego as erm_wzk')
            ->join('DocumentRelations as dr', 'dr.ID1', '=', 'erm_wzk.IDRuchuMagazynowego')
            ->join('ElementRuchuMagazynowego as erm_wz', function ($join) {
                $join->on('erm_wz.IDRuchuMagazynowego', '=', 'dr.ID2')
                    ->on('erm_wz.IDTowaru', '=', 'erm_wzk.IDTowaru');
            })
            ->where('dr.IDType1', 4)
            ->where('dr.IDType2', 2)

            ->whereColumn('erm_wzk.CenaJednostkowa', '!=', 'erm_wz.CenaJednostkowa')
            ->select('erm_wzk.IDRuchuMagazynowego as id1_WZk', 'erm_wzk.CenaJednostkowa as CenaWZk', 'erm_wz.IDRuchuMagazynowego as id2_WZ',  'erm_wz.CenaJednostkowa as CenaWZ', 'erm_wz.IDTowaru')
            ->get();
        $prices = [];
        foreach ($o_prices as $key => $row) {
            $prices[] = $row->id2_WZ;
        }
        $prices = array_unique($prices);

        $itemsToUpdate = DB::table('ElementRuchuMagazynowego as erm_wzk')
            ->join('DocumentRelations as dr', 'dr.ID1', '=', 'erm_wzk.IDRuchuMagazynowego')
            ->join('ElementRuchuMagazynowego as erm_wz', function ($join) {
                $join->on('erm_wz.IDRuchuMagazynowego', '=', 'dr.ID2')
                    ->on('erm_wz.IDTowaru', '=', 'erm_wzk.IDTowaru');
            })
            ->where('dr.IDType1', 4)
            ->where('dr.IDType2', 2)
            ->whereColumn('erm_wzk.CenaJednostkowa', '!=', 'erm_wz.CenaJednostkowa')
            ->whereNotIn('erm_wz.IDRuchuMagazynowego', function ($query) {
                $query->select('IDRuchuMagazynowego')
                    ->from('ElementRuchuMagazynowego')
                    ->groupBy('IDRuchuMagazynowego', 'IDTowaru')
                    ->havingRaw('COUNT(IDTowaru) > 1');
            })
            ->select('erm_wzk.IDElementuRuchuMagazynowego', 'erm_wz.CenaJednostkowa')
            ->get();

        // Подсчитываем количество замен
        $updatedRows = 0;

        // Обновляем записи
        foreach ($itemsToUpdate as $item) {
            DB::table('ElementRuchuMagazynowego')
                ->where('IDElementuRuchuMagazynowego', $item->IDElementuRuchuMagazynowego)
                ->update(['CenaJednostkowa' => $item->CenaJednostkowa]);
            $updatedRows++;
        }

        return response()->json(['message' => 'Set price OK', 'updatedRows' => $updatedRows, 'prices' => $prices], 200);
    }

    public function setCenaZLfromPZ()
    {
        // Получаем все документы с IDRodzajuRuchuMagazynowego = 27 и не пустым WartoscDokumentu
        $documents = DB::table('RuchMagazynowy as rm')
            ->where('rm.IDRodzajuRuchuMagazynowego', 27)
            ->whereNotNull('rm.WartoscDokumentu')
            ->get();

        $updatedRows = 0;

        foreach ($documents as $document) {
            // Получаем все товары для каждого документа
            $items = DB::table('ElementRuchuMagazynowego as erm')
                ->where('erm.IDRuchuMagazynowego', $document->IDRuchuMagazynowego)
                ->get();

            foreach ($items as $item) {
                // Получаем IDElementuPZ для каждого товара
                $relatedItem = DB::table('ZaleznosciPZWZ as pzwz')
                    ->where('pzwz.IDElementuWZ', $item->IDElementuRuchuMagazynowego)
                    ->first();

                if ($relatedItem) {
                    // Получаем цену связанного товара
                    $relatedItemPrice = DB::table('ElementRuchuMagazynowego')
                        ->where('IDElementuRuchuMagazynowego', $relatedItem->IDElementuPZ)
                        ->value('CenaJednostkowa');

                    if ($relatedItemPrice && $relatedItemPrice != $item->CenaJednostkowa) {
                        // Обновляем цену у текущего товара
                        DB::table('ElementRuchuMagazynowego')
                            ->where('IDElementuRuchuMagazynowego', $item->IDElementuRuchuMagazynowego)
                            ->update(['CenaJednostkowa' => $relatedItemPrice]);

                        // Обновляем цену у товара с IDRodzic = текущий товар
                        DB::table('ElementRuchuMagazynowego')
                            ->where('IDRodzic', $item->IDElementuRuchuMagazynowego)
                            ->update(['CenaJednostkowa' => $relatedItemPrice]);

                        $updatedRows++;
                    }
                }
            }
        }

        return response()->json(['message' => 'Set price OK', 'updatedRows' => $updatedRows], 200);
    }
}
