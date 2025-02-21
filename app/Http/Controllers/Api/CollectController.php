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
        //get collected orders
        $waiteOrders = $this->waitOrders($request->user);

        //get free orders
        $allOrders = DB::table('dbo.Orders as o')
            ->leftJoin('dbo.RodzajTransportu as rt', 'rt.IDRodzajuTransportu', '=', 'o.IDTransport')
            ->select('o.IDOrder', 'o.IDAccount', 'o.Date', 'o.Number', 'o.IDWarehouse', 'o.IDUser', 'o.IDTransport', 'rt.Nazwa as transport_name')
            ->where('o.IDOrderType', 15)
            ->where('o.IDOrderStatus', 23)
            ->when($waiteOrders, function ($query, $waiteOrders) {
                return $query->whereNotIn('o.IDOrder', $waiteOrders);
            })
            ->whereNotIn('o.IDOrder', function ($query) {
                $query->select('ID2')
                    ->from('dbo.DocumentRelations')
                    ->where('IDType1', 2)
                    ->where('IDType2', 15);
            })
            ->get();
        return response()->json(['allOrders' => $allOrders, 'waiteOrders' => $waiteOrders]);
    }

    private function waitOrders($user)
    {
        $waiteOrders = DB::table('collect')->where([
            'IDUzytkownika' => $user->IDUzytkownika,
            'status' => 0,
        ])->pluck('IDOrder');

        return $waiteOrders;
    }

    private function freeOrdersFromCollect($orders, $user)
    {
        $ordersInCollect = DB::table('collect as col')
            ->whereIn('col.IDOrder', $orders)
            ->pluck('IDOrder');
        $freeOrders = array_diff($orders, $ordersInCollect->toArray());

        // $waiteOrders = $this->waitOrders($user);
        // $freeOrders = array_merge($freeOrders, $waiteOrders->toArray());

        return $freeOrders;
    }

    public function getOrderProducts(Request $request)
    {

        $freeOrders = $this->freeOrdersFromCollect($request->IDsOrder, $request->user);

        $maxProducts = $request->maxProducts ?? 30;
        $maxWeight = $request->maxWeight ?? 30;
        $maxM3 = $request->maxM3 ?? 0.2;
        $selectedOrders = collect();
        $currentProducts = 0;
        $currentWeight = 0;
        $currentM3 = 0;
        foreach ($request->IDsWarehouses as  $warehouse) {

            $sharedItems = DB::table('dbo.OrderLines as ol')
                ->select('ol.IDItem', DB::raw('SUM(ol.Quantity) as TotalQuantity'), DB::raw('STRING_AGG(ol.IDOrder, \',\') as Orders'))
                ->leftJoin('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')
                ->leftJoin('Orders as o', 'o.IDOrder', '=', 'ol.IDOrder')
                ->where('o.IDWarehouse', $warehouse)
                ->where('t.Usluga', '!=', 1)
                ->whereIn('ol.IDOrder', $freeOrders)
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
                        'o._OrdersTempDecimal2 as NumberBL',
                        DB::raw('SUM(ol.Quantity) as TotalQuantity'),
                        DB::raw('COALESCE(SUM(t._TowarTempDecimal1 * ol.Quantity), 0) as TotalWeight'),
                        DB::raw('COALESCE(SUM(t._TowarTempDecimal2 * ol.Quantity), 0) as TotalM3')
                    )
                    ->where('o.IDWarehouse', $warehouse)
                    ->where('t.Usluga', '!=', 1)
                    ->whereIn('ol.IDOrder', $orders)
                    ->groupBy('o.IDOrder', 'o._OrdersTempDecimal2', 'o.IDWarehouse')
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
                    'o._OrdersTempDecimal2 as NumberBL',
                    DB::raw('SUM(ol.Quantity) as TotalQuantity'),
                    DB::raw('COALESCE(SUM(t._TowarTempDecimal1 * ol.Quantity), 0) as TotalWeight'),
                    DB::raw('COALESCE(SUM(t._TowarTempDecimal2 * ol.Quantity), 0) as TotalM3')
                )
                ->where('o.IDWarehouse', $warehouse)
                ->where('t.Usluga', '!=', 1)
                ->whereIn('ol.IDOrder', array_diff($freeOrders, $orders))
                ->groupBy('o.IDOrder', 'o._OrdersTempDecimal2', 'o.IDWarehouse')
                ->orderByDesc('TotalQuantity')
                ->get();
            $ordersData = $ordersData->merge($nextOrdersData);
            $ordersData = $ordersData->sortByDesc('IDWarehouse');



            $firstOrder = $ordersData->shift();
            if ($firstOrder) {
                $selectedOrders->push($firstOrder);
                $currentProducts = $firstOrder->TotalQuantity;
                $currentWeight = $firstOrder->TotalWeight;
                $currentM3 = $firstOrder->TotalM3;
            }

            // Going through the remaining orders until we exceed the limits
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
        }

        $selectedOrdersIDs = $selectedOrders->pluck('IDOrder');
        $listProducts = DB::table('dbo.OrderLines as ol')
            ->leftJoin('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')
            ->leftJoin('Orders as o', 'o.IDOrder', '=', 'ol.IDOrder')
            ->select('ol.IDItem', DB::raw('CAST(ol.Quantity AS INT) as Quantity'),  'ol.IDOrder', 'o.IDWarehouse', DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'), 't.Nazwa', 't.KodKreskowy', 't._TowarTempString1 as sku', 't._TowarTempDecimal1 as Waga', 't._TowarTempDecimal2 as m3')
            ->where('t.Usluga', '!=', 1)
            ->whereIn('ol.IDOrder', $selectedOrdersIDs)
            ->orderByDesc('o.IDWarehouse')
            // ->orderByDesc('ol.IDItem')
            ->orderByDesc('ol.IDOrder')
            ->get();

        $listProducts->each(function ($product) {
            $product->locations = app('App\Http\Controllers\Api\LocationController')->getProductLocations($product->IDItem)->pluck('LocationCode')->implode(',');
        });

        // Selected orders
        return response()->json([
            'listProducts' => $listProducts,
            'selectedOrders' => $selectedOrdersIDs,
            'sharedItems' => $sharedItems,
            'endParamas' => ['maxProducts' => $currentProducts, 'maxWeight' =>  $currentWeight, 'maxM3' => $currentM3],

        ]);
    }

    public function collectOrders(Request $request)
    {

        $freeOrders = $this->freeOrdersFromCollect($request->selectedOrders, $request->user);

        $makeOrders = [];
        foreach ($freeOrders as $IDOrder) {

            try {
                DB::table('collect')->insert([
                    'IDUzytkownika' => $request->user->IDUzytkownika,
                    'Date' => Carbon::now(),
                    'IDOrder' => $IDOrder,
                    'status' => 0,
                ]);

                $makeOrders[] = $IDOrder;
            } catch (\Throwable $th) {
                throw $th;
            }
        }

        if (count($request->selectedOrders) > count($freeOrders)) {
            $message = "Niektóre (" . (count($request->selectedOrders) - count($freeOrders)) . ") zamówienia zostały już dodane do zbioru";
        } else {
            $message = "Zamówienia zostały dodane do zbioru";
        }

        return response()->json([
            'message' => $message,
            'makeOrders' => $makeOrders
        ]);
    }

    public function deleteSelectedMakeOrders(Request $request)
    {
        $deletedOrders = DB::table('collect')
            ->whereIn('IDOrder', $request->selectedOrders)
            ->delete();

        return response()->json([
            'message' => 'Zamówienia zostały usunięte z zbioru',
            'makeOrders' => $this->waitOrders($request->user)
        ]);
    }
}
