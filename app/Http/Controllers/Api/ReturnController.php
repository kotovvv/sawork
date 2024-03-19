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

        $res['order'] =  collect(DB::select('SELECT [IDOrder] ,[Number] ,cast ([Created] as date) Created ,[IDOrderType],con.[Nazwa] cName FROM [dbo].[Orders] ord
        LEFT JOIN [dbo].[Kontrahent] con ON con.[IDKontrahenta] = ord.[IDAccount]
         WHERE [IDWarehouse] = ' . (int) $data['warehouse'] . ' AND \'' . $orderdata . '\' IN (_OrdersTempString2, Number, _OrdersTempString1, _OrdersTempString3, CONVERT(NVARCHAR(255), _OrdersTempDecimal1))'))->first();

        $res['wz'] = collect(
            DB::select('SELECT [ID1] FROM [dbo].[DocumentRelations] WHERE [ID2] = ' . $res['order']->IDOrder . ' AND [IDType1] = 2')
        )->first();

        if ($res['wz']) {
            $res['products'] =
                DB::select('SELECT [IDTowaru]
            ,[Ilosc]
            ,[Wydano]
           ,[CenaJednostkowa]
           ,tov.[Nazwa]
           ,tov.[KodKreskowy]
           ,tov.[_TowarTempString1]
           ,tov.[Zdjecie] img
       FROM [dbo].[ElementRuchuMagazynowego] wz
       LEFT JOIN [dbo].[Towar]  tov ON wz.[IDTowaru] = tov.[IDTowaru]
       WHERE [IDRuchuMagazynowego] = ' . $res['wz']->ID1);

            foreach ($res['products'] as $key => $product) {
                if ($product->img) {
                    $res['products'][$key]->img =  base64_encode($product->img);
                }
            }
        }
        return response($res);
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
