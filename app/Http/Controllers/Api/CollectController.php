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
        $IDsWaiteOrders = $waiteOrders->pluck('IDOrder');
        //get free orders
        $allOrders = DB::table('dbo.Orders as o')
            ->leftJoin('dbo.RodzajTransportu as rt', 'rt.IDRodzajuTransportu', '=', 'o.IDTransport')
            ->select('o.IDOrder', 'o.IDAccount', 'o.Date', 'o.Number', DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'), 'o.IDWarehouse', 'o.IDUser', 'o.IDTransport', 'rt.Nazwa as transport_name')
            ->where('o.IDOrderType', 15)
            ->where('o.IDOrderStatus', 23)
            ->when($IDsWaiteOrders, function ($query, $IDsWaiteOrders) {
                return $query->whereNotIn('o.IDOrder', $IDsWaiteOrders);
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
        $waiteOrders = DB::table('collect')
            ->leftJoin('Orders as o', 'o.IDOrder', '=', 'collect.IDOrder')
            ->where([
                'IDUzytkownika' => $user->IDUzytkownika,
                'status' => 0,
            ])
            ->select('o.IDOrder', 'o.IDAccount', 'o.Date', 'o.Number', DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'), 'o.IDWarehouse', 'o.IDUser', 'o.IDTransport')
            ->get();

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
                        DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'),
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
                    DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'),
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


            if (count($selectedOrders) == 0) {
                $firstOrder = $ordersData->shift();
                if ($firstOrder) {
                    $selectedOrders->push($firstOrder);
                    $currentProducts = $firstOrder->TotalQuantity;
                    $currentWeight = $firstOrder->TotalWeight;
                    $currentM3 = $firstOrder->TotalM3;
                }
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
        $listProducts = $this->getListProducts($selectedOrdersIDs);

        $listProducts->each(function ($product) {
            $locations = app('App\Http\Controllers\Api\LocationController')->getProductLocations($product->IDItem);
            $product->locations = $locations->pluck('LocationCode')->implode(',');
            $product->locationsData = $locations;
        });

        // Selected orders
        return response()->json([
            'listProducts' => $listProducts,
            'selectedOrders' => $selectedOrdersIDs,
            'sharedItems' => $sharedItems,
            'endParamas' => ['maxProducts' => $currentProducts, 'maxWeight' =>  $currentWeight, 'maxM3' => $currentM3],

        ]);
    }

    private function getListProducts($idsOrder)
    {
        return DB::table('dbo.OrderLines as ol')
            ->leftJoin('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')
            ->leftJoin('Orders as o', 'o.IDOrder', '=', 'ol.IDOrder')
            ->select('ol.IDItem', DB::raw('CAST(ol.Quantity AS INT) as Quantity'),  'ol.IDOrder', DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'), 'o.IDWarehouse', 't.Nazwa', 't.KodKreskowy', 't._TowarTempString1 as sku', 't._TowarTempDecimal1 as Waga', 't._TowarTempDecimal2 as m3')
            ->where('t.Usluga', '!=', 1)
            ->whereIn('ol.IDOrder', $idsOrder)
            ->orderByDesc('o.IDWarehouse')
            // ->orderByDesc('ol.IDItem')
            ->orderBy('o.Date', 'asc')
            ->get();
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

    public function getUserLocation($IDMagazynu, $IDUzytkownika)
    {
        $IDWarehouseLocation = DB::table('WarehouseLocations')
            ->where('IDMagazynu', $IDMagazynu)
            ->where('LocationCode', 'User' . $IDUzytkownika)
            ->value('IDWarehouseLocation');
        if (!$IDWarehouseLocation) {
            DB::table('WarehouseLocations')->insert([
                'IDMagazynu' => $IDMagazynu,
                'LocationCode' => 'User' . $IDUzytkownika,
            ]);
            $IDWarehouseLocation = DB::table('WarehouseLocations')
                ->where('IDMagazynu', $IDMagazynu)
                ->where('LocationCode', 'User' . $IDUzytkownika)
                ->value('IDWarehouseLocation');
        }
        return $IDWarehouseLocation;
    }

    private function changeProductsLocation($product, $toLocation, $IDUzytkownika, $createdDoc = null, $Uwagi = '')
    {

        $request = new Request([
            'IDTowaru' => $product['IDItem'],
            'qty' => $product['qty'],
            'fromLocation' => $product['fromLocaton'],
            'toLocation' => $toLocation,
            'selectedWarehause' => $product['IDMagazynu'],
            'createdDoc' => $createdDoc,
            'Uwagi' => $Uwagi,
            'IDUzytkownika' => $IDUzytkownika,
        ]);
        $response = app('App\Http\Controllers\Api\LocationController')->doRelokacja($request);

        if (isset($response['createdDoc'])) {
            return $response['createdDoc'];
        } else {
            return $response;
        }
    }

    public function prepareDoc(Request $request)
    {
        $messages = [];
        $productsERROR = [];
        $listProductsOK = [];
        $orderERROR = [];
        $IDsOrderERROR = [];
        $listOrders = [];
        $createdDoc = [];
        $Uwagi = 'User' . $request->user->IDUzytkownika . ' ' . date('Y-m-d H:i:s');
        // Пока склад
        foreach ($request->IDsWarehouses as  $IDMagazynu) {
            $createdDoc[$IDMagazynu] = null;
            // Локация пользователя
            $toLocation['IDWarehouseLocation'] = $this->getUserLocation($IDMagazynu, $request->user->IDUzytkownika);
            // Пока Заказы по складу
            foreach ($request->orders as $order) {
                // есл заказ не этого склада берём следующий
                if ($order['IDWarehouse'] != $IDMagazynu) {
                    continue;
                }
                $productsOK = [];
                $orderOK = true;
                $products = $this->getListProducts([$order['IDOrder']]);
                // Пока Товар
                foreach ($products as $product) {
                    $needqty = $product->Quantity;
                    // Локации товара
                    $locations = app('App\Http\Controllers\Api\LocationController')->getProductLocations($product->IDItem);

                    if ($locations) {
                        foreach ($locations as $location) {
                            //с каждой локации забираем нужное количество товара
                            $location_ilosc = $location['ilosc'];
                            if ($needqty >= $location_ilosc) {
                                $needqty = $needqty - $location_ilosc;
                                // код локации товара
                                // номер заказа в БЛ
                                $productsOK[] = ['IDMagazynu' => $IDMagazynu, 'IDOrder' => $order['IDOrder'], 'NumberBL' => $order['NumberBL'], 'IDItem' => $product->IDItem, 'qty' => $location_ilosc, 'fromLocaton' => ['IDWarehouseLocation' => $location['IDWarehouseLocation']], 'locationCode' => $location['LocationCode']];
                            } else {
                                $productsOK[] = ['IDMagazynu' => $IDMagazynu, 'IDOrder' => $order['IDOrder'], 'NumberBL' => $order['NumberBL'], 'IDItem' => $product->IDItem, 'qty' => $needqty, 'fromLocaton' =>  ['IDWarehouseLocation' => $location['IDWarehouseLocation']], 'locationCode' => $location['LocationCode']];
                                $needqty = 0;
                            }
                            if ($needqty == 0) {
                                break;
                            }
                        }
                    }
                    if ($needqty > 0) {
                        //  "оповестить о нехватке товара ,
                        // занести штрих код товара в уваги документа"
                        $productsERROR[] = ['IDMagazynu' => $IDMagazynu, 'IDOrder' => $order['IDOrder'], 'NumberBL' => $order['NumberBL'], 'IDItem' => $product->IDItem, 'qty' => $product->Quantity, 'Uwagi' => 'not enough products'];
                        $orderERROR[] = $order;
                        $IDsOrderERROR[] = $order['IDOrder'];
                        break;
                    }
                } //product
                //если имеются все товары заказа
                if (!in_array($order['IDOrder'], $IDsOrderERROR)) {
                    // товары заказа можно перемещать в локацию пользователя
                    foreach ($productsOK as $product) {
                        $res =  $this->changeProductsLocation($product, $toLocation, $request->user->IDUzytkownika, $createdDoc[$IDMagazynu], $Uwagi);
                        if (isset($res['idmin'])) {
                            $listProductsOK[] = $product;
                            $createdDoc[$IDMagazynu] = $res;
                        } else {
                            $orderOK = false;
                            $orderERROR[] = $order;
                            $productsERROR[] = ['IDMagazynu' => $IDMagazynu, 'IDOrder' => $order['IDOrder'], 'NumberBL' => $order['NumberBL'], 'IDItem' => $product['IDItem'], 'qty' => $product['Quantity'], 'Uwagi' => $res->message];
                            $messages[] = $res->message;
                            break;
                        }
                    }
                }
                if ($orderOK) {
                    $listOrders[] = $order;
                    $values = array_merge(array_column($createdDoc[$IDMagazynu], 'idmin'), array_column($createdDoc[$IDMagazynu], 'idpls'));
                    $comma_separated_values = implode(',', $values);
                    DB::table('collect')->insert([
                        'IDUzytkownika' => $request->user->IDUzytkownika,
                        'Date' => Carbon::now(),
                        'IDOrder' => $order['IDOrder'],
                        'status' => 0,
                        'created_doc' => $comma_separated_values,
                    ]);
                    // Изменить статус заказа на "Ожидает сборки"
                    // DB::table('Orders')->where('IDOrder', $order['IDOrder'])->update([
                    //     'IDOrderStatus' => 42,
                    // ]);
                } else {
                    // Изменить статус заказа на "Не хватает товаров"
                    // предусмотреть возможность отмены смены локаций
                    // DB::table('Orders')->where('IDOrder', $order['IDOrder'])->update([
                    //     'IDOrderStatus' => 37,
                    // ]);
                }
            } //order
        } //warehouse
        return response()->json([
            'messages' => $messages,
            'createdDoc' => $createdDoc,
            'productsERROR' => $productsERROR,
            'orderERROR' => $orderERROR,
            'listProductsOK' => $listProductsOK,
            'listOrdersBL' => $listOrders,
        ]);
    }
}
