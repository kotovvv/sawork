<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ReturnController extends Controller
{
    public function getWarehouse(Request $request)
    {
        if (isset($request->user)) {
            $user = $request->user;
            $a_mag = collect(DB::table('UprawnieniaDoMagazynow')->where('IDUzytkownika', $user->IDUzytkownika)->where('Uprawniony', 1)->pluck('IDMagazynu'))->toArray();

            return DB::table('Magazyn')
                ->select('IDMagazynu', 'Nazwa', 'Symbol', 'IDLokalizaciiZwrot', 'Zniszczony', 'Wznowienie')
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
        FROM [dbo].[Orders] ord
        LEFT JOIN [dbo].[Kontrahent] con ON con.[IDKontrahenta] = ord.[IDAccount]
         WHERE [IDWarehouse] = ' . (int) $data['warehouse'] . ' AND \'' . $orderdata . '\' IN (Number, _OrdersTempString1,_OrdersTempString2, _OrdersTempString3, _OrdersTempString4, CONVERT(NVARCHAR(255), _OrdersTempDecimal1),  CONVERT(NVARCHAR(255), CONVERT(INT, _OrdersTempDecimal2)))'))->first();

        if ($res['order']) {
            $res['wz'] = collect(
                DB::select('SELECT [ID1] as ID FROM [dbo].[DocumentRelations] WHERE [ID2] = ' . $res['order']->IDOrder . ' AND [IDType1] = 2')
            )->first();

            if ($res['wz']) {
                // check if wzk done
                $wzk = collect(
                    DB::select('SELECT [ID1] as ID FROM [dbo].[DocumentRelations] WHERE [ID2] = ' . $res['wz']->ID . ' AND [IDType2] = 2 AND [IDType1] = 4')
                )->first();
                if ($wzk) {
                    return response('Zwrot został już przetworzony dla ' . $orderdata, 202);
                }
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
                $products = [];
                foreach ($res['products'] as $key => $product) {

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
        $creat_wz['Uwagi'] = $order->Remarks;
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

        $ndoc = DB::selectOne("SELECT TOP 1 NrDokumentu n FROM dbo.[RuchMagazynowy] WHERE [IDRodzajuRuchuMagazynowego] = '4' AND IDMagazynu = " . $magazin_id . " AND year( Utworzono ) = " . date('Y') . " ORDER BY Data DESC");
        preg_match('/^WZk(.*)\/.*/', $ndoc->n, $a_ndoc);
        $creat_wz['NrDokumentu'] = 'WZk' . (int) $a_ndoc[1] + 1 . '/' . date('y') . ' - ' . $data["magazin"]["Symbol"];
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



            $tov = [];
            //for each product return
            foreach ($data['products'] as $key => $product) {
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
                                'Uwagi' => $product['message'],
                                'IDRuchuMagazynowego' => $wz->IDRuchuMagazynowego,
                                'Uzytkownik' => 1,
                                'IDUserCreated' => 1,
                                'IDWarehouseLocation' => $product['IDWarehouseLocation']
                            ];
                            $tovs[$key]->qty = 0;
                        }
                    }
                }
            }

            DB::table('dbo.ElementRuchuMagazynowego')->insert($tov);

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
        $data = $request->all();
        $IDWarehouse = trim($data['IDWarehouse']);
        $res = [];
        $res = DB::table('RuchMagazynowy')
            ->select('IDRuchuMagazynowego', 'NrDokumentu', 'Data', 'IDKontrahenta', 'IDCompany', 'IDUzytkownika', 'Operator', 'IDMagazynu', 'IDRodzajuRuchuMagazynowego', 'Uwagi', '_RuchMagazynowyTempDecimal1', '_RuchMagazynowyTempString2', '_RuchMagazynowyTempString1', '_RuchMagazynowyTempString4', '_RuchMagazynowyTempString5', '_RuchMagazynowyTempBool1')
            ->where('NrDokumentu', 'like', 'WZk%')
            // ->where('NrDokumentu', 'like', '%/24')
            ->where('IDMagazynu', $IDWarehouse)
            ->orderBy('Data', 'desc')
            ->get();
        return response($res);
    }
    public function getWZkProducts(Request $request)
    {
        $data = $request->all();
        $IDRuchuMagazynowego = trim($data['IDRuchuMagazynowego']);
        $res = [];
        $res = DB::table('ElementRuchuMagazynowego as erm')
            ->select('IDElementuRuchuMagazynowego', 'IDRuchuMagazynowego', 't.IDTowaru', 'Ilosc', 'erm.IDWarehouseLocation', DB::raw('t._TowarTempString1 as sku'), 't.KodKreskowy as KodKreskowy', 'wl.LocationCode', 't.Nazwa',)
            ->leftJoin('Towar as t', 'erm.IDTowaru', '=', 't.IDTowaru')
            ->leftJoin('dbo.WarehouseLocations as wl', 'wl.IDWarehouseLocation', '=', 'erm.IDWarehouseLocation')
            ->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)
            ->get();
        return response($res);
    }
}