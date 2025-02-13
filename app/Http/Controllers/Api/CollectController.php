<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class CollectController extends Controller
{

    public function getAllOrders(Request $request)
    {
        $orders = DB::table('dbo.Orders as o')
            ->leftJoin('dbo.RodzajTransportu as rt', 'rt.IDRodzajuTransportu', '=', 'o.IDTransport')
            ->select('o.IDOrder', 'o.IDAccount', 'o.Date', 'o.Number', 'o.IDWarehouse', 'o.IDUser', 'o.IDTransport', 'rt.Nazwa as transport_name')
            ->where('o.IDOrderType', 15)
            ->where('o.IDOrderStatus', 23)
            ->whereNotIn('o.IDOrder', function ($query) {
                $query->select('ID2')
                    ->from('dbo.DocumentRelations')
                    ->where('IDType1', 2)
                    ->where('IDType2', 15);
            })
            ->get();

        return response()->json($orders);
    }

    public function getOrderProducts(Request $request)
    {
        $products = DB::table('dbo.OrderLines as ol')
            ->join('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')
            ->select('ol.IDItem', DB::raw('SUM(ol.Quantity) as total_quantity'), 't.Nazwa', 't.KodKreskowy', 't._TowarTempString1 as sku')
            ->where('t.Usluga', '!=', 1)
            ->whereIn('ol.IDOrder', $request->IDsOrder)
            ->groupBy('ol.IDItem', 't.Nazwa', 't.KodKreskowy', 't._TowarTempString1')
            ->get();

        return response()->json($products);
    }
}
