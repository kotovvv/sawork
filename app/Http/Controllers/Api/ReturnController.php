<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReturnController extends Controller
{

    public function logUsers($user, $what, $ip = 0)
    {
        $myLogInfo = date('Y-m-d H:i:s') . ', ' . $ip . ', ' . $user->NazwaUzytkownika . ', ' . $what;
        file_put_contents(
            storage_path() . '/logs/useReport.log',
            $myLogInfo . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function getWarehouse(Request $request)
    {
        if (isset($request->user)) {
            $user = $request->user;
            $a_mag = collect(DB::table('UprawnieniaDoMagazynow')->where('IDUzytkownika', $user->IDUzytkownika)->where('Uprawniony', 1)->pluck('IDMagazynu'))->toArray();

            return DB::table('Magazyn')
                ->select('IDMagazynu', 'Nazwa', 'Symbol', 'IDLokalizaciiZwrot', 'Zniszczony', 'Naprawa')
                ->leftJoin('EMailMagazyn', 'Magazyn.IDMagazynu', '=', 'EMailMagazyn.IDMagazyn')
                ->whereIn('IDMagazynu', $a_mag)
                ->get();
        }
        return response('Nou', 400);
    }

    public function getOrder(Request $request)
    {
        $data = $request->all();
        $orderdata = trim($data['ordername']);
        $res = [];


        $res['order'] =  collect(DB::select('SELECT
        [IDOrder]
        , [Number]
        , cast ([Created] as date) Created
        , con.[Nazwa] cName
        , CAST([_OrdersTempDecimal2] AS INT) as pk
        , [Remarks] as Uwagi
        ,[_OrdersTempString7] as Zrodlo
        ,[_OrdersTempString8] as External_id
        ,[_OrdersTempString9] as Login_klienta
        FROM [dbo].[Orders] ord
        LEFT JOIN [dbo].[Kontrahent] con ON con.[IDKontrahenta] = ord.[IDAccount]
         WHERE [IDWarehouse] = ' . (int) $data['warehouse'] . ' AND \'' . $orderdata . '\' IN (Number, _OrdersTempString1,_OrdersTempString2, _OrdersTempString3, _OrdersTempString4, CONVERT(NVARCHAR(255), _OrdersTempDecimal1),  CONVERT(NVARCHAR(255), CONVERT(INT, _OrdersTempDecimal2)))'))->first();

        if ($res['order']) {
            $res['wz'] = collect(
                DB::select('SELECT [ID1] as ID FROM [dbo].[DocumentRelations] WHERE [ID2] = ' . $res['order']->IDOrder . ' AND [IDType1] = 2')
            )->first();

            if ($res['wz']) {
                $res['products'] =
                    DB::select('SELECT tov.[IDTowaru]
            , [Ilosc] Quantity
            , [Wydano] Wydano
           ,[CenaJednostkowa]
           ,[IDWarehouseLocation]
           ,tov.[Nazwa]
           ,tov.[KodKreskowy]
           ,tov.[_TowarTempString1]
           ,tov.[Zdjecie] img
       FROM [dbo].[ElementRuchuMagazynowego] wz
       LEFT JOIN [dbo].[Towar] tov ON wz.[IDTowaru] = tov.[IDTowaru]
       WHERE tov.[Usluga] != 1 AND [IDRuchuMagazynowego] = ' . $res['wz']->ID);

                // check if wzk done
                $wzk = collect(DB::table('DocumentRelations')->where('ID2', $res['wz']->ID)->where('IDType2', 2)->where('IDType1', 4)->pluck('ID1'))->toArray();

                if (count($wzk)) {

                    // get returned product
                    $returnedProducts = collect(DB::table('ElementRuchuMagazynowego')->whereIn('IDRuchuMagazynowego',  $wzk)->select('IDTowaru', 'Ilosc')->get())->toArray();
                    foreach ($res['products'] as  $product) {
                        $returnedProduct = array_filter($returnedProducts, function ($item) use ($product) {
                            return $item->IDTowaru == $product->IDTowaru;
                        });
                        if ($returnedProduct) {
                            $returnedProduct = array_shift($returnedProduct);
                            $product->Quantity -= $returnedProduct->Ilosc;
                        }
                    }

                    // return response('Zwrot został już przetworzony dla ' . $orderdata, 202);
                }

                $products = [];
                foreach ($res['products'] as $key => $product) {
                    if ($product->Quantity == 0) continue;
                    $code = $product->IDTowaru;
                    if (!isset($products[$code])) {
                        if ($product->img) {
                            $res['products'][$key]->img =  base64_encode($product->img);
                        }
                        $products[$code] = $product;
                    } else {
                        $products[$code]->Quantity += $product->Quantity;
                    }
                }
                $res['products'] = $products;
            }
        }
        return response($res);
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

    public function doWz(Request $request)
    {
        $data = $request->all();
        $doc_wz = $data['wz'];
        $magazin_id = (int) $data['magazin']['IDMagazynu'];

        // $IDWarehouseLocation = [10 => 148, 11 => 731, 17 => 954, 16 => 953][$magazin_id];
        //$IDWarehouseLocation = DB::table('dbo.EMailMagazyn')->where('IDMagazyn', $magazin_id)->where('IDLokalizaciiZwrot', '>', 0)->value('IDLokalizaciiZwrot');

        //get tovs
        $tovs = collect(DB::select('SELECT "Ilosc" qty,   "CenaJednostkowa" price,   "IDTowaru" cod,     "IDOrderLine" nl FROM "dbo"."ElementRuchuMagazynowego" WHERE IDRuchuMagazynowego = ' . (int) $doc_wz['ID'] . ' ORDER BY "CenaJednostkowa"'))->toArray();

        // пока о.кол
        // ищим е.код == о.код
        // если е.кол >= о.кол
        // е.кол = е.кол - о.кол
        // о.кол= о.кол - е.кол
        // отдаём по е.price
        // если е.кол == 0 удалить строку
        $order =  collect(DB::select("SELECT * FROM [dbo].[Orders] WHERE [IDOrder] = " . (int) $data['order_id']))->first();

        $creat_wz = [];
        $creat_wz['Data'] = date('Y/m/d H:i:s');
        // $creat_wz['Utworzono'] = Now();
        // $creat_wz['Zmodyfikowano'] = Now();
        $creat_wz['IDRodzajuRuchuMagazynowego'] = 4;
        $creat_wz['IDMagazynu'] = $magazin_id;
        $creat_wz['IDUzytkownika'] = 1;
        $creat_wz['Operator'] = 1;
        $creat_wz['IDCompany'] = 1;
        $creat_wz['IDKontrahenta'] = (int) $order->IDAccount;
        $creat_wz['Uwagi'] = $data['order_Uwagi'];
        $creat_wz['_RuchMagazynowyTempDecimal1'] = $order->_OrdersTempDecimal2;
        $creat_wz['_RuchMagazynowyTempString2'] = $order->_OrdersTempString1;
        $creat_wz['_RuchMagazynowyTempString1'] = $order->_OrdersTempString2;
        $creat_wz['_RuchMagazynowyTempString4'] = $order->_OrdersTempString4;
        $creat_wz['_RuchMagazynowyTempString5'] = $order->_OrdersTempString5;

        $creat_wz['_RuchMagazynowyTempBool1'] = $data['full'];

        // в таблице dbo.RuchMagazynowy создаем строку
        // [Data] текущая дата/время
        // Utworzono текущая дата/время
        // Zmodyfikowano текущая дата/время
        // IDRodzajuRuchuMagazynowego=4
        // IDMagazynu = из шапки
        // NrDokumentu = номер/год
        // IDKontrahenta
        // IDUzytkownika = пользователь БД
        // Operator = 1
        // IDCompany = из WZ

        // $ndoc = DB::selectOne("SELECT TOP 1 NrDokumentu n FROM dbo.[RuchMagazynowy] WHERE [IDRodzajuRuchuMagazynowego] = '4' AND IDMagazynu = " . $magazin_id . " AND year( Utworzono ) = " . date('Y') . " ORDER BY Data DESC");
        // preg_match('/^WZk(.*)\/.*/', $ndoc->n, $a_ndoc);
        // $creat_wz['NrDokumentu'] = 'WZk' . (int) $a_ndoc[1] + 1 . '/' . date('y') . ' - ' . $data["magazin"]["Symbol"];
        $creat_wz['NrDokumentu'] = $this->lastNumber('WZk', $data["magazin"]["Symbol"]);
        // check if NrDokumentu exist in base
        if (DB::select("select * from dbo.RuchMagazynowy where NrDokumentu = '" . $creat_wz['NrDokumentu'] . "'")) {
            return response($creat_wz['NrDokumentu'] . ' Został już utworzony', 200);
        }

        $wz = DB::table('dbo.RuchMagazynowy')->insert($creat_wz);
        if ($wz) {
            $wz = DB::select("select * from dbo.RuchMagazynowy where NrDokumentu = '" . $creat_wz['NrDokumentu'] . "'")[0];
            // dd($wz);
            // в dbo.ElementRuchuMagazynowego
            // Ilosc = количество товара
            // Uwagi = комментарий
            // CenaJednostkowa = цена за 1шт из WZ
            // IDRuchuMagazynowego = таблица dbo.RuchMagazynowy столбец IDRuchuMagazynowego
            // IDTowaru
            // Utworzono = текущая дата/время
            // Zmodyfikowano = текущая дата/время
            // Uzytkownik = пользователь БД
            // IDUserCreated = пользователь БД

            // get locations name
            $loc_name =  (array) DB::table('EMailMagazyn')
                ->where('IDMagazyn', $magazin_id)
                ->select('IDLokalizaciiZwrot as ok', 'Zniszczony', 'Naprawa')
                ->first();
            $loc_name = array_flip($loc_name);

            $tov = [];
            $locations = [];
            //for each product return
            foreach ($data['products'] as $k => $product) {
                $ocol = $product['qty']; //how many need return

                while ($ocol) {
                    //find cod in all products
                    foreach ($tovs as $key => $ep) {
                        if ($ep->cod == $product['IDTowaru'] && $ep->qty > 0 && $ocol > 0) {
                            //$qty = 0;

                            if ($ocol <= $ep->qty) {

                                $qty = $ocol;
                                $ocol = 0;
                            } else {
                                $ocol = $ocol - $ep->qty;
                                $qty = $ep->qty;
                            }

                            $tov[] = [
                                'IDTowaru' => $product['IDTowaru'],
                                'Ilosc' => (int) $qty,
                                'CenaJednostkowa' => $ep->price,
                                'Uwagi' => $product['Uwagi'],
                                'IDRuchuMagazynowego' => $wz->IDRuchuMagazynowego,
                                'Uzytkownik' => 1,
                                'IDUserCreated' => 1,
                                'IDWarehouseLocation' => $product['IDWarehouseLocation']
                            ];
                            $locations[] = $loc_name[$product['IDWarehouseLocation']] ?? $product['IDWarehouseLocation'];
                            $tovs[$key]->qty = $ep->qty - $ocol;
                        }
                    }
                }
            }

            DB::table('dbo.ElementRuchuMagazynowego')->insert($tov);
            $locations = array_unique($locations);
            DB::table('dbo.infoComming')->updateOrInsert([
                'IDRuchuMagazynowego' => $wz->IDRuchuMagazynowego
            ], [
                'locations' => implode(',', $locations)
            ]);

            // dbo.DocumentRelations
            // ID1 = таблица dbo.RuchMagazynowy столбец IDRuchuMagazynowego
            // IDType1 = 4
            // ID2 = Id WZ
            // IDType2 = 2
            $rel = [
                'ID1' => $wz->IDRuchuMagazynowego,
                'IDType1' => 4,
                'ID2' => $data['wz']['ID'],
                'IDType2' => 2
            ];
            if (DB::table('dbo.DocumentRelations')->insert($rel)) {
                return response($creat_wz['NrDokumentu'] . ' Dokument został pomyślnie utworzony', 200);
            }
        }
        return response('Błąd(', 500);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getDocsWZk(Request $request)
    {
        $this->logUsers($request->user, 'Wzrot', $request->ip());

        $data = $request->all();
        $IDWarehouse = trim($data['IDWarehouse']);
        $dateMin = Carbon::parse($data['dateMin'])->setTime(00, 00, 00)->format('m.d.Y H:i:s');
        $dateMax = Carbon::parse($data['dateMax'])->setTime(23, 59, 59)->format('m.d.Y H:i:s');

        $res = [];
        $res['DocsWZk'] = DB::table('RuchMagazynowy as rm')
            ->select(
                'rm.IDRuchuMagazynowego',
                'NrDokumentu',
                'Data',
                'rm.IDKontrahenta',
                'IDCompany',
                'IDUzytkownika',
                'Operator',
                'IDMagazynu',
                'IDRodzajuRuchuMagazynowego',
                'rm.Uwagi',
                '_RuchMagazynowyTempDecimal1',
                '_RuchMagazynowyTempString2',
                '_RuchMagazynowyTempString1',
                '_RuchMagazynowyTempString4',
                '_RuchMagazynowyTempString5',
                '_RuchMagazynowyTempString6 as uwagiSprzedawcy',
                '_RuchMagazynowyTempBool1',
                '_RuchMagazynowyTempBool3 as isWartosc',
                'kon.Nazwa as Kontrahent',
                'ic.photo as photo',
                'ic.locations as status'
                // DB::raw('MAX(ic.photo) as photo'),
                // DB::raw('MAX(CAST(ic.locations AS VARCHAR(MAX))) as status')
            )
            ->leftJoin('Kontrahent as kon', 'rm.IDKontrahenta', '=', 'kon.IDKontrahenta')
            ->leftJoin('InfoComming as ic', 'ic.IDRuchuMagazynowego', '=', 'rm.IDRuchuMagazynowego')
            ->where('NrDokumentu', 'like', 'WZk%')
            ->where('rm.IDMagazynu', $IDWarehouse)
            ->whereBetween('Data', [$dateMin, $dateMax])
            ->orderBy('Data', 'DESC')
            ->get();

        $WarehouseLocations = DB::table('dbo.EMailMagazyn')
            ->select('IDLokalizaciiZwrot', 'Zniszczony', 'Naprawa')
            ->where('IDMagazyn', $IDWarehouse)
            ->where('IDLokalizaciiZwrot', '>', 0)
            ->first();

        $res['Zwrot'] = count($this->arrayProductsInLocation($WarehouseLocations->IDLokalizaciiZwrot));
        $res['Zniszczony'] = count($this->arrayProductsInLocation($WarehouseLocations->Zniszczony));
        $res['Naprawa'] = count($this->arrayProductsInLocation($WarehouseLocations->Naprawa));
        return response($res);
    }

    private function arrayProductsInLocation($IDWarehouseLocation)
    {
        $date = Carbon::now()->format('Y/m/d H:i:s');

        $param = 1; // 0 = Nazvanie, 1 = KodKreskowy
        $query = "SELECT dbo.StockInLocation(?, ?, ?) AS Stock";
        $result = DB::select($query, [$IDWarehouseLocation, $date, $param]);
        $resultString = $result[0]->Stock ?? null;
        $array = [];

        if ($resultString) {
            $pairs = explode(', ', $resultString);
            foreach ($pairs as $pair) {
                list($key, $value) = explode(': ', $pair);
                $array[$key] = (int) $value;
            }
        }
        return $array;
    }

    public function getProductsInLocation($IDWarehouse, $location)
    {
        $location = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
        $IDWarehouse = htmlspecialchars($IDWarehouse, ENT_QUOTES, 'UTF-8');
        $date = Carbon::now()->format('Y/m/d');
        $WarehouseLocations = DB::table('dbo.EMailMagazyn')
            ->select('IDLokalizaciiZwrot', 'Zniszczony', 'Naprawa')
            ->where('IDMagazyn', $IDWarehouse)
            ->where('IDLokalizaciiZwrot', '>', 0)
            ->first();

        //$arrayKodKreskowy = array_map('strval', array_keys($this->arrayProductsInLocation($WarehouseLocations->$location)));

        $locationsActive = DB::table('Ustawienia')->where('Nazwa', 'WarehouseLocations')->value('Wartosc');
        if ($locationsActive !== 'Tak') {
            return [];
        }

        $subQuery = DB::table('ElementRuchuMagazynowego as e')
            ->join('Towar as t', 't.IDTowaru', '=', 'e.IDTowaru')
            ->join(DB::raw('dbo.MostRecentOBDate(?) as BO'), function ($join) use ($date) {
                $join->on('t.IDMagazynu', '=', 'BO.IDMagazynu')
                    ->addBinding($date);
            })
            ->join('RuchMagazynowy as r', 'e.IDRuchuMagazynowego', '=', 'r.IDRuchuMagazynowego')
            ->leftJoin('ZaleznosciPZWZ as pzwz', 'pzwz.IDElementuPZ', '=', 'e.IDElementuRuchuMagazynowego')
            ->leftJoin('ElementRuchuMagazynowego as ewz', 'ewz.IDElementuRuchuMagazynowego', '=', 'pzwz.IDElementuWZ')
            ->leftJoin('RuchMagazynowy as wz', 'ewz.IDRuchuMagazynowego', '=', 'wz.IDRuchuMagazynowego')
            ->where('r.Operator', '>', 0)
            ->where('r.Data', '<', $date)
            ->where('r.Data', '>=', DB::raw('BO.MinDate'))
            ->where('e.IDWarehouseLocation', $WarehouseLocations->$location)
            ->where('t.Usluga', 0)
            ->select(
                'r.NrDokumentu',
                'r.Data',
                't.Nazwa',
                't._TowarTempString1',
                't.KodKreskowy',
                'e.Uwagi',
                'e.IDElementuRuchuMagazynowego',
                DB::raw('SUM(CASE WHEN pzwz.Ilosc IS NULL THEN e.Ilosc ELSE -pzwz.Ilosc END) as ilosc')
            )
            ->groupBy('r.NrDokumentu', 'r.Data', 't.Nazwa', 't._TowarTempString1', 't.KodKreskowy', 'e.Uwagi', 'e.IDElementuRuchuMagazynowego')
            ->havingRaw('SUM(CASE WHEN pzwz.Ilosc IS NULL THEN e.Ilosc ELSE -pzwz.Ilosc END) > 0');

        // Основной запрос для получения деталей товаров
        $details = DB::table(DB::raw("({$subQuery->toSql()}) as Q"))
            ->mergeBindings($subQuery)
            ->leftJoin('dbo.WarehouseLocations as w', 'w.IDWarehouseLocation', '=', DB::raw($WarehouseLocations->$location))
            ->select(
                'Q.NrDokumentu',
                'Q.Data',
                'Q.Nazwa',
                'Q._TowarTempString1 as SKU',
                'Q.KodKreskowy',
                'Q.Uwagi',
                'Q.IDElementuRuchuMagazynowego',
                DB::raw('SUM(Q.ilosc) as ilosc')
            )
            ->groupBy('Q.NrDokumentu', 'Q.Data', 'Q.Nazwa', 'Q._TowarTempString1', 'Q.KodKreskowy', 'Q.Uwagi', 'Q.IDElementuRuchuMagazynowego', 'w.LocationCode')
            ->get();

        return $details;
    }


    public function getWZkProducts(Request $request)
    {
        $data = $request->all();
        $IDRuchuMagazynowego = trim($data['IDRuchuMagazynowego']);

        $res = [];
        $res['wzk_products'] = DB::table('ElementRuchuMagazynowego as erm')
            ->select('IDElementuRuchuMagazynowego', 'erm.Uwagi', 'IDRuchuMagazynowego', 't.IDTowaru', 'Ilosc', 'erm.IDWarehouseLocation', DB::raw("CASE WHEN wl.LocationCode LIKE '_wrot%' THEN 'ok' ELSE wl.LocationCode END as status"), DB::raw('t._TowarTempString1 as sku'), 't.KodKreskowy as KodKreskowy', 'wl.LocationCode', 't.Nazwa')
            ->leftJoin('Towar as t', 'erm.IDTowaru', '=', 't.IDTowaru')
            ->leftJoin('dbo.WarehouseLocations as wl', 'wl.IDWarehouseLocation', '=', 'erm.IDWarehouseLocation')
            ->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)
            ->get();

        $res['client'] =  DB::table('Orders')->select(
            'IDOrder',
            'Number',
            'con.Nazwa',
            'con.Telefon',
            '_OrdersTempString7 as Zrodlo',
            '_OrdersTempString8 as External_id',
            '_OrdersTempString9 as Login_klienta'
        )
            ->leftJoin('Kontrahent as con', 'con.IDKontrahenta', '=', 'Orders.IDAccount')
            ->where('IDOrder', DB::table('DocumentRelations')->select('ID2')->where('ID1', DB::table('DocumentRelations')->where('ID1', $IDRuchuMagazynowego)->where('IDType1', 4)->where('IDType2', 2)->value('ID2'))->where('IDType1', 2)->value('ID1'))->first();

        return response($res);
    }

    public function saveUwagiSprz(Request $request)
    {
        $data = $request->all();
        $IDRuchuMagazynowego = trim($data['IDRuchuMagazynowego']);
        $Uwagi = trim($data['uwagiSprzedawcy']);
        $isWartosc = trim($data['isWartosc']);
        $res = [];
        $res = DB::table('RuchMagazynowy')
            ->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)
            ->update(['_RuchMagazynowyTempString6' => $Uwagi, '_RuchMagazynowyTempBool3' => $isWartosc]);
        return response($res);
    }

    public function saveUwagiDoc(Request $request)
    {
        $data = $request->all();
        $IDRuchuMagazynowego = trim($data['IDRuchuMagazynowego']);
        $Uwagi = trim($data['Uwagi']);
        $res = [];
        $res = DB::table('RuchMagazynowy')
            ->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)
            ->update(['Uwagi' => $Uwagi]);
        return response($res);
    }
    public function saveUwagiProduct(Request $request)
    {
        $data = $request->all();
        $IDElementuRuchuMagazynowego = trim($data['IDElementuRuchuMagazynowego']);

        $Uwagi = trim($data['Uwagi']);
        $res = [];
        $res = DB::table('ElementRuchuMagazynowego')
            ->where('IDElementuRuchuMagazynowego', $IDElementuRuchuMagazynowego)
            ->update(['Uwagi' => $Uwagi]);
        return response($res);
    }

    public function sendEmail(Request $request)
    {
        $validatedData = $request->validate([
            'NrDokumentu' => 'required|string|max:255',
            'IDRuchuMagazynowego' => 'required|integer',
        ]);

        $folder_name = 'zwrot';

        $data = [
            'title' => 'Photo zwrot od odbiorcy ' . date('Y-m-d'),
        ];
        $magazin = [];
        $my = DB::selectOne("SELECT  k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.Kontrahent k WHERE k.IDKontrahenta = 1");

        $docsWZk = DB::select("SELECT rm.IDRuchuMagazynowego, rm.Data, rm.Uwagi, rm.IDMagazynu, rm.NrDokumentu, rm.IDKontrahenta, rm.IDUzytkownika, rm.WartoscDokumentu, k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon
FROM dbo.RuchMagazynowy rm
LEFT JOIN dbo.Kontrahent k ON (k.IDKontrahenta = rm.IDKontrahenta)
WHERE NrDokumentu = '" .  $validatedData['NrDokumentu'] . "'");

        foreach ($docsWZk as $key => $docWZk) {
            $forpdf = [];
            if ($docWZk->IDUzytkownika != 1) {
                $forpdf['my'] = DB::selectOne("SELECT  k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.Kontrahent k WHERE k.IDKontrahenta = " . $docWZk->IDUzytkownika);
            } else {
                $forpdf['my'] = $my;
            }
            $forpdf['docWZk'] = $docWZk;
            $forpdf['Magazyn'] = DB::selectOne("SELECT Nazwa FROM dbo.Magazyn WHERE IDMagazynu = " . $docWZk->IDMagazynu);
            $email = DB::selectOne("SELECT eMailAddress FROM dbo.EMailMagazyn WHERE IDMagazyn = " . $docWZk->IDMagazynu);
            $forpdf['products'] = DB::select("SELECT t.Nazwa, t.KodKreskowy, erm.Uwagi, erm.Ilosc, erm.CenaJednostkowa, jm.Nazwa ed FROM ElementRuchuMagazynowego erm LEFT JOIN dbo.Towar t ON (erm.IDTowaru = t.IDTowaru) left JOIN JednostkaMiary jm ON (t.IDJednostkiMiary =  jm.IDJednostkiMiary) WHERE IDRuchuMagazynowego = " . $docWZk->IDRuchuMagazynowego);
            //generating pdf with user data
            $pdf = Pdf::loadView('mail', $forpdf);

            $magazin[$forpdf['Magazyn']->Nazwa]['pdfs'][] = $pdf;
            $magazin[$forpdf['Magazyn']->Nazwa]['ndoc'][] = $docWZk->NrDokumentu;
            $magazin[$forpdf['Magazyn']->Nazwa]['email'] = $email;

            //for log email
            $email_log = [
                'IDMagazynu' => $docWZk->IDMagazynu,
                'NrDokumentu' => $docWZk->NrDokumentu,
                'Status' => 2,
                'IDRuchuMagazynowego' => $docWZk->IDRuchuMagazynowego
            ];

            DB::table('dbo.EMailLog')->insert($email_log);
            //sleep(10);
        }
        foreach ($magazin as $key => $mag) {
            //send mail to user
            $emails = explode(',', $mag['email']->eMailAddress);

            if ($mag['email']) {
                Mail::send('message', $data, function ($message) use ($mag, $data, $emails, $validatedData, $folder_name) {
                    $message->from(env('MAIL_FROM_ADDRESS'));
                    $message->to($emails);
                    $message->subject($data['title']);
                    foreach ($mag['pdfs'] as $n => $pdf) {
                        $message->attachData($pdf->output(), $mag['ndoc'][$n] . '.pdf'); //attached pdf file
                    }
                    $path =  $validatedData['IDRuchuMagazynowego'] . '/' . $folder_name . '/';

                    // Get all files from the directory
                    $files = Storage::disk('public')->allFiles($path);

                    foreach ($files as $file) {
                        $message->attachData(Storage::disk('public')->get($file), basename($file));
                    }
                });
            }
        }
        return response("Photo Zwrot wysłane: ", '200');
    }

    public function whenSendedEmail(Request $request)
    {
        $data = $request->all();
        $IDRuchuMagazynowego = trim($data['IDRuchuMagazynowego']);
        $res = [];
        $res = DB::table('EMailLog')
            ->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)
            ->where('Status', 2)

            ->select(DB::raw("FORMAT(Data, 'yyyy-MM-dd HH:mm') as Data"))
            ->orderBy('Data', 'desc')
            ->get();
        return response($res);
    }
}
