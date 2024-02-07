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
    public function getOrder($IDWarehouse, $ordername)
    {
        // return DB::connection()->getDatabaseName();
        $order = [];
        $order['info'] =  DB::select('SELECT [IDOrder]
           ,[Number]
            ,[Created]
            ,[IDOrderType]
       FROM [dbo].[Orders] WHERE [IDWarehouse] = ' . $IDWarehouse . ' AND [IDOrder] = ' . $ordername);
        if ($order['info']) {
            $order['products'] =
                DB::select('SELECT [IDOrderLine]
           ,[IDItem]
            ,[Quantity]
       FROM [dbo].[OrderLines] WHERE [IDOrder] = ' . $ordername);
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
