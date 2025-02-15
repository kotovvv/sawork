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
        $maxProducts = $request->maxProducts ?? 30;
        $maxWeight = $request->maxWeight ?? 30;
        $maxM3 = $request->maxM3 ?? 0.2;

        $sharedItems = DB::table('dbo.OrderLines as ol')
            ->select('ol.IDItem', DB::raw('SUM(ol.Quantity) as TotalQuantity'), DB::raw('STRING_AGG(ol.IDOrder, \',\') as Orders'))
            ->leftJoin('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')
            ->where('t.Usluga', '!=', 1)
            ->whereIn('ol.IDOrder', $request->IDsOrder)
            ->groupBy('ol.IDItem')
            ->havingRaw('COUNT(DISTINCT ol.IDOrder) > 1')
            ->orderByDesc('TotalQuantity')
            ->get();

        $orders = [];
        $sharedItems->each(function ($item) use (&$orders) {
            $orders = array_merge($orders, explode(',', $item->Orders));
        });

        $orders = array_unique($orders);

        $ordersData = collect();
        if (count($orders) > 0) {

            $ordersData = DB::table('dbo.OrderLines as ol')
                ->leftJoin('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')
                ->leftJoin('Orders as o', 'o.IDOrder', '=', 'ol.IDOrder')
                ->select(
                    'o.IDOrder',
                    'o.IDWarehouse',
                    DB::raw('SUM(ol.Quantity) as TotalQuantity'),
                    DB::raw('COALESCE(SUM(t._TowarTempDecimal1 * ol.Quantity), 0) as TotalWeight'),
                    DB::raw('COALESCE(SUM(t._TowarTempDecimal2 * ol.Quantity), 0) as TotalM3')
                )
                ->where('t.Usluga', '!=', 1)
                ->whereIn('ol.IDOrder', $orders)
                ->groupBy('o.IDOrder', 'o.IDWarehouse')
                ->orderBy(DB::raw("CASE " . implode(" ", array_map(function ($id, $index) {
                    return "WHEN o.IDOrder = $id THEN $index";
                }, $orders, array_keys($orders))) . " END"))
                ->get();
        }

        $nextOrdersData =  DB::table('dbo.OrderLines as ol')
            ->leftJoin('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')
            ->leftJoin('Orders as o', 'o.IDOrder', '=', 'ol.IDOrder')
            ->select(
                'o.IDOrder',
                'o.IDWarehouse',
                DB::raw('SUM(ol.Quantity) as TotalQuantity'),
                DB::raw('COALESCE(SUM(t._TowarTempDecimal1 * ol.Quantity), 0) as TotalWeight'),
                DB::raw('COALESCE(SUM(t._TowarTempDecimal2 * ol.Quantity), 0) as TotalM3')
            )
            ->where('t.Usluga', '!=', 1)
            ->whereIn('ol.IDOrder', array_diff($request->IDsOrder, $orders))
            ->groupBy('o.IDOrder', 'o.IDWarehouse')
            ->orderByDesc('TotalQuantity')
            ->get();
        $ordersData = $ordersData->merge($nextOrdersData);



        $selectedOrders = collect();
        $currentProducts = 0;
        $currentWeight = 0;
        $currentM3 = 0;

        // Берём первый заказ с наибольшим количеством товаров
        $firstOrder = $ordersData->shift();
        if ($firstOrder) {
            $selectedOrders->push($firstOrder);
            $currentProducts = $firstOrder->TotalQuantity;
            $currentWeight = $firstOrder->TotalWeight;
            $currentM3 = $firstOrder->TotalM3;
        }

        // Перебираем оставшиеся заказы, пока не превысим ограничения
        foreach ($ordersData as $order) {
            if (
                ($currentProducts + $order->TotalQuantity <= $maxProducts) &&
                ($currentWeight + $order->TotalWeight <= $maxWeight) &&
                ($currentM3 + $order->TotalM3 <= $maxM3)
            ) {
                $selectedOrders->push($order);
                $currentProducts += $order->TotalQuantity;
                $currentWeight += $order->TotalWeight;
                $currentM3 += $order->TotalM3;
            }
        }
        $selectedOrdersIDs = $selectedOrders->pluck('IDOrder');
        $listProducts = DB::table('dbo.OrderLines as ol')
            ->leftJoin('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')
            ->leftJoin('Orders as o', 'o.IDOrder', '=', 'ol.IDOrder')
            ->select('ol.IDItem', 'ol.IDOrder', 'o.IDWarehouse', 'ol.Quantity', 't.Nazwa', 't.KodKreskowy', 't._TowarTempString1 as sku', 't._TowarTempDecimal1 as Waga', 't._TowarTempDecimal2 as m3')
            ->where('t.Usluga', '!=', 1)
            ->whereIn('ol.IDOrder', $selectedOrdersIDs)
            ->get();

        // Выбранные заказы
        // return $selectedOrders;
        return response()->json([
            'listProducts' => $listProducts,
            'selectedOrders' => $selectedOrdersIDs,
            'sharedItems' => $sharedItems,
            'endParamas' => ['maxProducts' => $currentProducts, 'maxWeight' =>  $currentWeight, 'maxM3' => $currentM3],

        ]);
    }
}
