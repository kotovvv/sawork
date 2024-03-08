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
        $order = [];
        //      $order['info'] =  collect(DB::select('SELECT [IDOrder] ,[Number] ,[Created] ,[IDOrderType] FROM [dbo].[Orders] WHERE [IDWarehouse] = ' . (int) $data['warehouse'] . ' AND ([Number] = \'' . $orderdata . '\' OR [_OrdersTempString3] = \'' . $orderdata . '\' OR [_OrdersTempString2] = \'' . $orderdata . '\' OR [_OrdersTempDecimal1] = \'' . $orderdata . '\''))->first();
        $order['info'] =  collect(DB::select('SELECT [IDOrder] ,[Number] ,[Created] ,[IDOrderType] FROM [dbo].[Orders] WHERE [IDWarehouse] = ' . (int) $data['warehouse'] . ' AND \'' . $orderdata . '\' IN (_OrdersTempString2, Number, _OrdersTempString1, _OrdersTempString3, CONVERT(NVARCHAR(255), _OrdersTempDecimal1))'))->first();

        if ($order['info']) {
            $order['products'] =
                DB::select('SELECT [IDOrderLine]
           ,[IDItem]
           ,tov.[Nazwa]
            ,[Quantity]
       FROM [dbo].[OrderLines] ord LEFT JOIN [dbo].[Towar]  tov ON ord.[IDItem] = tov.[IDTowaru] WHERE [IDOrder] = ' . $order['info']->IDOrder);
        }
        return $order;
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
