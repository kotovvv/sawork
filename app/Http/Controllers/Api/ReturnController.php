<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function getWarehouse()
    {
        // return DB::connection()->getDatabaseName();
        return DB::select('SELECT [IDMagazynu]
           ,[Nazwa]
        --    ,[Utworzono]
        --    ,[Zmodyfikowano]
           ,[Symbol]
        --    ,[Hidden]
        --    ,[NegativeStock]
       FROM [dbo].[Magazyn]');
    }
    public function getOrder(Request $request)
    {
        $data = $request->all();
        $orderdata = trim($data['ordername']);
        $res = [];
        //      $res['info'] =  collect(DB::select('SELECT [IDOrder] ,[Number] ,[Created] ,[IDOrderType] FROM [dbo].[Orders] WHERE [IDWarehouse] = ' . (int) $data['warehouse'] . ' AND ([Number] = \'' . $orderdata . '\' OR [_OrdersTempString3] = \'' . $orderdata . '\' OR [_OrdersTempString2] = \'' . $orderdata . '\' OR [_OrdersTempDecimal1] = \'' . $orderdata . '\''))->first();

        $res['order'] =  collect(DB::select('SELECT [IDOrder] ,[Number] ,cast ([Created] as date) Created ,[IDOrderType],con.[Nazwa] cName, [IDAccount] FROM [dbo].[Orders] ord
        LEFT JOIN [dbo].[Kontrahent] con ON con.[IDKontrahenta] = ord.[IDAccount]
         WHERE [IDWarehouse] = ' . (int) $data['warehouse'] . ' AND \'' . $orderdata . '\' IN (_OrdersTempString2, Number, _OrdersTempString1, _OrdersTempString3, CONVERT(NVARCHAR(255), _OrdersTempDecimal1))'))->first();

        $res['wz'] = collect(
            DB::select('SELECT [ID1] as ID FROM [dbo].[DocumentRelations] WHERE [ID2] = ' . $res['order']->IDOrder . ' AND [IDType1] = 2')
        )->first();

        if ($res['wz']) {
            $res['products'] =
                DB::select('SELECT tov.[IDTowaru]
            ,[Ilosc] Quantity
            ,[Wydano]
           ,[CenaJednostkowa]
           ,tov.[Nazwa]
           ,tov.[KodKreskowy]
           ,tov.[_TowarTempString1]
           ,tov.[Zdjecie] img
       FROM [dbo].[ElementRuchuMagazynowego] wz
       LEFT JOIN [dbo].[Towar]  tov ON wz.[IDTowaru] = tov.[IDTowaru]
       WHERE [IDRuchuMagazynowego] = ' . $res['wz']->ID);

            foreach ($res['products'] as $key => $product) {
                if ($product->img) {
                    $res['products'][$key]->img =  base64_encode($product->img);
                }
            }
        }
        return response($res);
    }

    public function doWz(Request $request)
    {
        $data = $request->all();


        $creat_wz = [];
        $creat_wz['Data'] = date('d/m/Y H:i:s');
        // $creat_wz['Utworzono'] = Now();
        // $creat_wz['Zmodyfikowano'] = Now();
        $creat_wz['IDRodzajuRuchuMagazynowego'] = 4;
        $creat_wz['IDMagazynu'] = $data['magazin']['IDMagazynu'];
        $creat_wz['IDKontrahenta'] = (int) $data['order']['IDAccount'];
        $creat_wz['IDUzytkownika'] = 1;
        $creat_wz['Operator'] = 1;
        $creat_wz['IDCompany'] = 1;

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
        // WartoscDokumentu = сумма товаров * кол
        // $sum = 0;
        // foreach ($data['products'] as $product) {
        //     $sum += $product['Quantity'] * $product['CenaJednostkowa'];
        // }

        // $creat_wz['WartoscDokumentu'] = $sum;
        $ndoc = DB::select("SELECT COUNT(*) n FROM dbo.[RuchMagazynowy] WHERE [IDRodzajuRuchuMagazynowego] = '4' AND IDMagazynu = " . $data['magazin']['IDMagazynu'] . " AND year( Utworzono ) = " . date('Y'));
        $creat_wz['NrDokumentu'] = 'WZk' . $ndoc[0]->n + 1 . '/' . date('y') . ' - ' . $data["magazin"]["Symbol"];
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
            foreach ($data['products'] as $product) {
                $tov[] = [
                    'Ilosc' => $product['qty'],
                    'CenaJednostkowa' => $product['CenaJednostkowa'],
                    'Uwagi' => $product['message'],
                    'IDRuchuMagazynowego' => $wz->IDRuchuMagazynowego,
                    'IDTowaru' => $product['IDTowaru'],
                    // 'Utworzono' => GETDATE(),
                    // 'Zmodyfikowano' => GETDATE(),
                    'Uzytkownik' => 1,
                    'IDUserCreated' => 1
                ];
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
}
