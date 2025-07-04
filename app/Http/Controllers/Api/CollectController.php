<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use App\Models\Collect;

class CollectController extends Controller
{

    public function getAllOrders(Request $request)
    {
        $user_role = $request->user->IDRoli;
        $problem = $request->input('problem', false);


        //get collected orders
        $waiteOrders = $this->waitOrders($request->user);
        //get locked orders
        $lokedOrders = $this->getLockedOrderIds($request->user);
        //get waite orders IDs
        $IDsWaiteOrders = $waiteOrders->pluck('IDOrder');
        //get free orders
        $allOrders = DB::table('dbo.Orders as o')
            ->leftJoin('dbo.RodzajTransportu as rt', 'rt.IDRodzajuTransportu', '=', 'o.IDTransport')
            ->select(
                'o.IDOrder',
                'o.IDAccount',
                'o.Date',
                'o.Number',
                'o.Remarks',
                DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'),
                'o.IDWarehouse',
                'o.IDUser',
                DB::raw("
                    CASE
                    WHEN rt.IDgroup IS NULL THEN rt.IDRodzajuTransportu
                    ELSE rt.IDgroup
                    END as IDTransport
                "),
                DB::raw("
                    CASE
                    WHEN rt.IDgroup IS NULL THEN rt.Nazwa
                    ELSE (
                        SELECT Nazwa FROM RodzajTransportu WHERE IDRodzajuTransportu = rt.IDgroup
                    )
                    END as transport_name
                ")
            )
            ->where('o.IDOrderType', 15)
            ->where('o.IDOrderStatus', 23)
            ->when($IDsWaiteOrders, function ($query, $IDsWaiteOrders) {
                return $query->whereNotIn('o.IDOrder', $IDsWaiteOrders);
            })

            ->whereNotNull('o._OrdersTempDecimal2') //Nr. Baselinker
            ->when(!in_array('o._OrdersTempString1', ['personal_Product replacement', 'personal_Blogger', 'personal_Reklamacja, ponowna wysyłka']), function ($query) {
                return $query->whereNotNull('o._OrdersTempString1'); //Nr. Faktury BL);
            })
            ->whereNotNull('o._OrdersTempString7') //Źródło
            ->where(function ($query) {
                $query->where('o._OrdersTempString5', '')
                    ->orWhereNull('o._OrdersTempString5');
            }) //Product_Chang
            ->whereNotIn('o.IDOrder', function ($query) {
                $query->select('ID2')
                    ->from('dbo.DocumentRelations')
                    ->where('IDType1', 2)
                    ->where('IDType2', 15);
            })
            ->when($lokedOrders, function ($query) use ($lokedOrders) {
                return $query->whereNotIn('o.IDOrder', $lokedOrders);
            })
            ->get();
        if ($user_role != 1) {
            // Получаем IDOrder из $allOrders
            $allOrderIds = $allOrders->pluck('IDOrder')->toArray();

            // Получаем валидные IDOrder из второй базы, только для тех, что есть в $allOrders
            $validOrderIds = DB::connection('second_mysql')->table('order_details as od')
                ->leftJoin('for_ttn as f', function ($join) {
                    $join->on('od.order_source_id', '=', 'f.order_source_id')
                        ->on('od.IDWarehouse', '=', 'f.id_warehouse')
                        ->on('od.order_source', '=', 'f.order_source')
                        ->on('od.delivery_method', '=', 'f.delivery_method');
                })
                ->whereIn('od.order_id', $allOrderIds)
                ->where(function ($query) {
                    $query->where('f.courier_code', '!=', '')
                        ->orWhere('f.account_id', '!=', 0);
                })
                ->groupBy('od.order_id')
                ->havingRaw('COUNT(od.order_id) > 0')
                ->pluck('od.order_id')
                ->toArray();

            // Фильтруем $allOrders по валидным IDOrder
            $allOrders = $allOrders->where(function ($order) use ($validOrderIds) {
                return in_array($order->IDOrder, $validOrderIds);
            })->values();
        } else if ($problem) {
            //get orders with problem
            $allOrderIds = $allOrders->pluck('IDOrder')->toArray();

            $validOrderIds = DB::connection('second_mysql')->table('order_details as od')
                ->leftJoin('for_ttn as f', function ($join) {
                    $join->on('od.order_source_id', '=', 'f.order_source_id')
                        ->on('od.IDWarehouse', '=', 'f.id_warehouse')
                        ->on('od.order_source', '=', 'f.order_source')
                        ->on('od.delivery_method', '=', 'f.delivery_method');
                })
                ->whereIn('od.order_id', $allOrderIds)
                ->where(function ($query) {
                    $query->where('f.courier_code', '!=', '')
                        ->orWhere('f.account_id', '!=', 0);
                })
                ->groupBy('od.order_id')
                ->havingRaw('COUNT(od.order_id) > 0')
                ->pluck('od.order_id')
                ->toArray();

            // Фильтруем $allOrders по проблемным IDOrder
            $allOrders = $allOrders->where(function ($order) use ($validOrderIds) {
                return !in_array($order->IDOrder, $validOrderIds);
            })->values();
        }

        return response()->json(['allOrders' => $allOrders, 'waiteOrders' => $waiteOrders]);
    }

    public function getLockedOrderIds($user, $foruser = null)
    {
        $locked = [];
        foreach (Cache::getMemcached()->getAllKeys() as $key) {
            if (str_starts_with($key, 'fulstor_cache_:order_lock_')) {
                $orderId = str_replace('fulstor_cache_:order_lock_', '', $key);
                $lock = Cache::get(str_replace('fulstor_cache_:', '', $key));
                if ($lock  && $foruser == null  ? $lock['user_id'] != $user->IDUzytkownika : true) {
                    $locked[] = $orderId;
                }
            }
        }
        return $locked;
    }

    private function waitOrders($user)
    {
        $o_waiteOrders = Collect::query()
            ->where([
                'IDUzytkownika' => $user->IDUzytkownika,
                'status' => 0,
            ])
            ->pluck('IDOrder');
        $waiteOrders = DB::table('orders as o')
            ->whereIn('o.IDOrder', $o_waiteOrders)
            ->select(
                'o.IDOrder',
                'o.IDAccount',
                'o.Date',
                'o.Number',
                'o.Remarks',
                DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'),
                'o.IDWarehouse',
                'o.IDUser',
                'o.IDTransport'
            )
            ->get();

        return $waiteOrders;
    }


    private function freeOrdersFromCollect($orders, $user)
    {
        $ordersInCollect = Collect::query()
            ->whereIn('IDOrder', $orders)
            ->pluck('IDOrder');

        $freeOrders = array_diff($orders, $ordersInCollect->toArray());


        return $freeOrders;
    }

    public function lockOrders($orders, $user)
    {
        $userId = $user->IDUzytkownika;
        $locked = [];
        $unavailable = [];

        foreach ($orders as $orderId) {
            $key = "order_lock_{$orderId}";
            $lock = Cache::get($key);


            if ($lock && $lock['user_id'] !== $userId) {
                $unavailable[] = $orderId;
            } else {
                Cache::put($key, [
                    'user_id' => $userId,
                    'locked_at' => now(),
                ], now()->addMinutes(2));
                $locked[] = $orderId;
            }
        }
        $this->getLockedOrderIds($user); // Refresh the locked orders cache

        return response()->json([
            'locked' => $locked,
            'unavailable' => $unavailable,
        ]);
    }

    public function getOrderProductsToCollect(Request $request)
    {

        $freeOrders = $this->freeOrdersFromCollect($request->IDsOrder, $request->user);
        // Check for intersection between $freeOrders and $lockedOrders
        $lockedOrders = $this->getLockedOrderIds($request->user);

        $intersectedOrders = array_intersect($freeOrders, $lockedOrders);
        if (!empty($intersectedOrders)) {
            return response()->json([
                'message' => 'Aktualizacja listy zamówień',
            ]);
        }
        $maxProducts = $request->maxProducts ?? 30;
        $maxWeight = $request->maxWeight ?? 30;
        $maxM3 = $request->maxM3 ?? 0.2;
        $selectedOrders = collect();
        $currentProducts = 0;
        $currentWeight = 0;
        $currentM3 = 0;
        $message = '';
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

        $lockedOrdersResponse = $this->lockOrders($selectedOrdersIDs->toArray(), $request->user);
        $lockedOrdersData = $lockedOrdersResponse->getData(true);

        // Selected orders
        return response()->json([
            'listProducts' => $listProducts,
            'selectedOrders' => $selectedOrdersIDs,
            'sharedItems' => $sharedItems,
            'endParamas' => ['maxProducts' => $currentProducts, 'maxWeight' =>  $currentWeight, 'maxM3' => $currentM3],
            'message' => $message,
            'selectedOrdersData' => $selectedOrders
        ]);
    }

    private function getListProducts($idsOrder)
    {
        $listProductsOK = DB::table('dbo.OrderLines as ol')
            ->leftJoin('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')
            ->leftJoin('Orders as o', 'o.IDOrder', '=', 'ol.IDOrder')
            ->select('ol.IDItem', DB::raw('CAST(ol.Quantity AS INT) as Quantity'),  'ol.IDOrder', DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'), 'o.IDWarehouse', 't.Nazwa', 't.KodKreskowy as EAN', 't._TowarTempString1 as SKU', 't._TowarTempDecimal1 as Waga', 't._TowarTempDecimal2 as m3')
            ->where('t.Usluga', '!=', 1)
            ->whereIn('ol.IDOrder', $idsOrder)
            ->orderByDesc('o.IDWarehouse')
            // ->orderByDesc('ol.IDItem')
            ->orderBy('o.Date', 'asc')
            ->get();

        return $listProductsOK;
    }

    public function collectOrders(Request $request)
    {
        Carbon::setLocale('pl');
        $freeOrders = $this->freeOrdersFromCollect($request->selectedOrders, $request->user);

        $makeOrders = [];
        foreach ($freeOrders as $IDOrder) {
            try {
                Collect::create([
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
        $deletedOrders = Collect::query()
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

    private function changeProductsLocation($product, $qty, $toLocation, $IDUzytkownika, $createdDoc = null, $Uwagi = '')
    {

        $request = new Request([
            'IDTowaru' => $product['IDItem'],
            'fromLocation' => $product['fromLocation'],
            'selectedWarehause' => $product['selectedWarehause'],
            'qty' => $qty,
            'toLocation' => $toLocation,
            'createdDoc' => $createdDoc,
            'Uwagi' =>  $Uwagi,
            'IDUzytkownika' => $IDUzytkownika,
        ]);
        $response = app('App\Http\Controllers\Api\LocationController')->doRelokacja($request);

        if (isset($response['createdDoc'])) {
            return $response;
        } else {
            Log::error('Error created Doc');
            throw new \Exception('Error created Doc');
        }
    }

    private function getToken($IDMagazynu)
    {
        return DB::table('settings')->where('obj_name', 'sklad_token')->where('key', $IDMagazynu)->value('value');
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

        foreach ($request->IDsWarehouses as $IDMagazynu) {

            if (env('APP_ENV') != 'local') {
                $token = $this->getToken($IDMagazynu);

                if ($token) {
                    $BL = new BaseLinkerController($token);
                    $bl_user_id = DB::table('settings')->where('obj_name', 'ext_id')->where('for_obj', $IDMagazynu)->where('key', $request->user->IDUzytkownika)->value('value');
                } else {
                    $messages[] = 'no token ' . $IDMagazynu;
                    continue;
                }
            } else {
                $BL = null;
                $bl_user_id = 0;
            }

            // // Check if all requested orders are locked (must match exactly)
            $lockedOrders = $this->getLockedOrderIds($request->user, true);
            $requestedOrderIds = collect($request->orders)->pluck('IDOrder')->toArray();

            if (count(array_intersect($lockedOrders, $requestedOrderIds)) == 0) {
                return response()->json([
                    'message' => 'Zaktualizuj listę zamówień, niektóre zamówienia są już zablokowane.',
                ]);
            }

            $createdDoc[$IDMagazynu] = null;
            $toLocation['IDWarehouseLocation'] = $this->getUserLocation($IDMagazynu, $request->user->IDUzytkownika);

            foreach ($request->orders as $order) {
                if ($order['IDWarehouse'] != $IDMagazynu) {
                    continue;
                }
                $invoices = [];
                if (env('APP_ENV') != 'local') {
                    $parameters = [
                        'order_id' => $order['NumberBL'],
                        'get_unconfirmed_orders' => true,
                        'include_custom_extra_fields' => true,
                    ];
                    if (!$BL->inRealizacji($parameters)) {
                        $messages[] = 'Order BL ' . $order['NumberBL'] . ' ne W realizacji';
                        continue;
                    }
                    $invoices = $BL->getInvoices(['order_id' => $order['NumberBL']]);

                    //Log::info("invoices: " . json_encode($invoices));
                }

                $orderOK = true;
                $remarks = $order['Remarks'];
                $products = $this->getListProducts([$order['IDOrder']]);
                $Uwagi = 'User' . $request->user->IDUzytkownika . ' || ' . $order['Remarks'];
                try {
                    DB::transaction(function () use ($order, $IDMagazynu, $toLocation, $request, $BL, $bl_user_id, &$messages, &$productsERROR, &$orderERROR, &$IDsOrderERROR, &$listProductsOK, &$listOrders, &$createdDoc, $Uwagi, &$orderOK, &$products, $invoices, $remarks) {
                        $IDsElementuRuchuMagazynowego = [];
                        $orderProductsOK = [];

                        foreach ($products as $product) {

                            $needqty = $product->Quantity;
                            $locations = app('App\Http\Controllers\Api\LocationController')->getProductLocations($product->IDItem);

                            if ($locations) {
                                foreach ($locations as $location) {
                                    $result = [];
                                    $item = ['IDItem' => $product->IDItem, 'fromLocation' => ['IDWarehouseLocation' => $location['IDWarehouseLocation']], 'selectedWarehause' => $IDMagazynu];
                                    $location_ilosc = $location['ilosc'];
                                    $qtyToMove = min($needqty, $location_ilosc);
                                    $needqty -= $qtyToMove;
                                    $orderProductsOK[] = [
                                        'IDMagazynu' => $IDMagazynu,
                                        'IDOrder' => $order['IDOrder'],
                                        'NumberBL' => $order['NumberBL'],
                                        'IDItem' => $product->IDItem,
                                        'qty' => $qtyToMove,
                                        'fromLocaton' => ['IDWarehouseLocation' => $location['IDWarehouseLocation']],
                                        'locationCode' => $location['LocationCode'],
                                        'Nazwa' => $product->Nazwa,
                                        'EAN' => $product->EAN,
                                        'SKU' => $product->SKU
                                    ];

                                    $result = $this->changeProductsLocation($item, $qtyToMove, $toLocation, $request->user->IDUzytkownika, $createdDoc[$IDMagazynu], $Uwagi);
                                    if (isset($result['createdDoc']['idmin'])) {
                                        $createdDoc[$IDMagazynu] = $result['createdDoc'];
                                        $IDsElementuRuchuMagazynowego[$product->IDItem]['min'] = array_merge(
                                            $IDsElementuRuchuMagazynowego[$product->IDItem]['min'] ?? [],
                                            $result['IDsElementuRuchuMagazynowego']['min']
                                        );
                                        $IDsElementuRuchuMagazynowego[$product->IDItem]['pls'] = array_merge(
                                            $IDsElementuRuchuMagazynowego[$product->IDItem]['pls'] ?? [],
                                            $result['IDsElementuRuchuMagazynowego']['pls']
                                        );
                                    } else {
                                        $orderOK = false;
                                        $orderERROR[] = $order;
                                        $productsERROR[] = ['IDMagazynu' => $IDMagazynu, 'IDOrder' => $order['IDOrder'], 'NumberBL' => $order['NumberBL'], 'IDItem' => $product['IDItem'], 'qty' => $product['Quantity'], 'Uwagi' => $result->message];
                                        Log::error('Błąd zmiany lokalizacji produktów #BL: ' . $order['NumberBL'] . ' Nazwa: ' . $product->Nazwa . ' EAN: ' . $product->EAN . ' SKU: ' . $product->SKU);
                                        throw new \Exception('Błąd zmiany lokalizacji produktów #BL: ' . $order['NumberBL'] . ' Nazwa: ' . $product->Nazwa . ' EAN: ' . $product->EAN . ' SKU: ' . $product->SKU);
                                    }
                                    if ($needqty <= 0) {
                                        break;
                                    }
                                }
                            }
                            if ($needqty > 0) {
                                $orderOK = false;
                                $productsERROR[] = ['IDMagazynu' => $IDMagazynu, 'IDOrder' => $order['IDOrder'], 'NumberBL' => $order['NumberBL'], 'IDItem' => $product->IDItem, 'qty' => $product->Quantity, 'Uwagi' => 'za mało produktów'];
                                $orderERROR[] = $order;
                                $IDsOrderERROR[] = $order['IDOrder'];
                                Log::error('Niewystarczająca ilość #BL: ' . $order['NumberBL'] . ' Nazwa: ' . $product->Nazwa . ' EAN: ' . $product->EAN . ' SKU: ' . $product->SKU);
                                throw new \Exception('Niewystarczająca ilość #BL: ' . $order['NumberBL'] . ' Nazwa: ' . $product->Nazwa . ' EAN: ' . $product->EAN . ' SKU: ' . $product->SKU);
                            }
                        }

                        if ($orderOK) {
                            $listOrders[] = $order;
                            $listProductsOK = array_merge($listProductsOK, $orderProductsOK);
                            if (isset($invoices['invoices'])) {
                                $invoice_id = collect($invoices['invoices'])->firstWhere('order_id',  $order['NumberBL'])['invoice_id'] ?? null;
                                $invoice_number = collect($invoices['invoices'])->firstWhere('order_id',  $order['NumberBL'])['number'] ?? null;
                            } else {
                                $invoice_id =  null;
                                $invoice_number = null;
                                $messages[] = 'Error getting invoices for order: ' . $order['NumberBL'];
                                //throw new \Exception('Error getting invoices for order: ' . $order['NumberBL']);
                            }






                            //Log::info("invoice_id", [$invoice_id]);

                            $inserted = Collect::query()->insert([
                                'IDUzytkownika' => $request->user->IDUzytkownika,
                                'Date' => Carbon::now(),
                                'IDOrder' => $order['IDOrder'],
                                'status' => 0,
                                'created_doc' => json_encode($createdDoc[$IDMagazynu]),
                                'IDsElementuRuchuMagazynowego' => json_encode($IDsElementuRuchuMagazynowego),
                                'invoice_id' =>   $invoice_id
                            ]);

                            if (!$inserted) {
                                throw new \Exception('Error inserting into collect table');
                            }
                            if (env('APP_ENV') != 'local') {

                                //Download invoice pdf
                                if ($invoice_id) {
                                    $token = $this->getToken($IDMagazynu);
                                    //Log::info("printing invoice", [$token]);
                                    \App\Jobs\DownloadInvoicePdf::dispatch($IDMagazynu, $invoice_number, $invoice_id, $token);
                                }

                                $parameters = [
                                    'order_id' => $order['NumberBL'],
                                    'status_id' => $BL->status_id_Kompletowanie,
                                ];
                                $response = $BL->setOrderStatus($parameters);
                                //\Log::info('setOrderStatus response in baselinker:', $response);
                                if (!$response['status'] == 'SUCCESS') {
                                    $messages[] = 'Error for order: ' . $order['NumberBL'];
                                    throw new \Exception('Error setting order fields in BL');
                                }

                                $parameters = [
                                    'order_id' => $order['NumberBL'],
                                    'custom_extra_fields' => [$BL->id_exfield_stan => $bl_user_id],
                                    //'admin_comments' => 'Zamówienie zrealizowane przez system',
                                ];
                                $response = $BL->setOrderFields($parameters);
                                if (!$response['status'] == 'SUCCESS') {
                                    $messages[] = 'Error for order: ' . $order['NumberBL'];
                                    throw new \Exception('Error setting order fields in BL');
                                }
                            }
                            $updateted = DB::table('Orders')->where('IDOrder', $order['IDOrder'])->update([
                                'IDOrderStatus' => 42,
                            ]);
                            if (!$updateted) {
                                throw new \Exception('Error updateted into Orders table');
                            }
                        }
                    });
                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                    $messages[] = $e->getMessage();
                    if (env('APP_ENV') != 'local') {
                        $parameters = [
                            'order_id' => $order['NumberBL'],
                            'status_id' => $BL->status_id_Nie_wysylac,
                        ];
                        $BL->setOrderStatus($parameters);
                        $parameters = [
                            'order_id' => $order['NumberBL'],
                            'admin_comments' => $e->getMessage(),
                        ];
                        $BL->setOrderFields($parameters);
                    }
                    DB::table('Orders')->where('IDOrder', $order['IDOrder'])->update([
                        'IDOrderStatus' => 29, //Nie wysyłać
                        'Remarks' => $remarks . ' ' . $e->getMessage(),
                    ]);
                    //throw $e;
                }
            }
        }

        $result = [];

        //foreach ($listProductsOK as $location) {
        // \Log::info('location', $location);
        foreach ($listProductsOK as $item) {
            //\Log::info('item', $item);
            $key = $item['EAN'] . '_' . $item['locationCode'];
            if (isset($result[$key])) {
                $result[$key]['qty'] += $item['qty'];
            } else {
                $result[$key] = [
                    'qty' => $item['qty'],
                    'EAN' => $item['EAN'],
                    'locationCode' => $item['locationCode'],
                    'Nazwa' => $item['Nazwa'],
                    'SKU' => $item['SKU'],
                    'NumberBL' => $item['NumberBL'],
                    'IDItem' => $item['IDItem'],
                ];
            }
        }
        // }

        $listProductsOK = array_values($result);

        return response()->json([
            'messages' => $messages,
            'createdDoc' => $createdDoc,
            'productsERROR' => $productsERROR,
            'orderERROR' => $orderERROR,
            'listProductsOK' => $listProductsOK,
            'listOrdersBL' => $listOrders,
        ]);
    }

    public function generatePZfromOrder__NOTUSE(Request $request)
    {
        $DocumentType = $request->DocumentType;
        $UserID = $request->UserID;
        $AmountFlag = $request->AmountFlag;
        $ElementsGUID = $request->ElementsGUID;
        $OrderID = $request->OrderID;

        try {
            DB::beginTransaction();

            $order = DB::table('dbo.Orders')->where('IDOrder', $OrderID)->first();
            if (!$order) {
                throw new \Exception('SQL proc - GeneratePZfromOrder: no source document.');
            }

            $OrderType = $order->IDOrderType;
            if (DB::table('dbo.GetBaseDocumentTypeID')->where('IDOrderType', $OrderType)->value('IDBaseDocumentType') != 15) {
                throw new \Exception('SQL proc - [GenerateWZfromOrder]: wrong document type.');
            }
            if (DB::table('dbo.GetBaseDocumentTypeID')->where('IDOrderType', $DocumentType)->value('IDBaseDocumentType') != 2) {
                throw new \Exception('SQL proc - [GenerateWZfromOrder]: wrong dst document type.');
            }

            $DocDate = Carbon::now();
            $InvAlgGross = DB::table('Ustawienia')->where('Nazwa', 'InvoiceAlgorithm')->value('Wartosc') == 'Brutto' ? 1 : 0;
            $PricesModeGross = DB::table('Ustawienia')->where('Nazwa', 'PricesMode')->value('Wartosc') == 'Brutto' ? 1 : 0;

            $WarehouseID = $order->IDWarehouse;
            $AccountID = $order->IDAccount;
            $CostID = $order->IDCostType;
            $TransportID = $order->IDTransport;
            $TransportNr = $order->TransportNumber;
            $Remarks = $order->Remarks;
            $PaymentTypeID = $order->IDPaymentType;
            $IDCompany = $order->IDCompany;
            $CurrencyID = $order->IDCurrency;
            $CurrencyRateID = $order->IDCurrencyRate;

            if (DB::table('Ustawienia')->where('Nazwa', 'UseCurrenciesOnPZ')->value('Wartosc') != 1) {
                $CurrencyID = null;
                $CurrencyRateID = null;
            }

            $DocumentID = $request->DocumentID;
            if (!DB::table('dbo.RuchMagazynowy')->where('IDRuchuMagazynowego', $DocumentID)->exists()) {
                $DocumentID = DB::table('dbo.RuchMagazynowy')->insertGetId([
                    'Data' => $DocDate,
                    'Uwagi' => $Remarks,
                    'IDRodzajuRuchuMagazynowego' => $DocumentType,
                    'IDMagazynu' => $WarehouseID,
                    'NrDokumentu' => $request->DocumentNumber,
                    'IDKontrahenta' => $AccountID,
                    'IDUzytkownika' => $UserID,
                    'IDGrupyKosztow' => $CostID,
                    'IDRodzajuTransportu' => $TransportID,
                    'TransportNumber' => $TransportNr,
                    'IDPaymentType' => $PaymentTypeID,
                    'IDCompany' => $IDCompany,
                    'IDCurrency' => $CurrencyID,
                    'IDCurrencyRate' => $CurrencyRateID,
                ]);
            } else {
                $existingDocument = DB::table('dbo.RuchMagazynowy')->where('IDRuchuMagazynowego', $DocumentID)->first();
                if ($existingDocument->IDRodzajuRuchuMagazynowego != $DocumentType) {
                    throw new \Exception('SQL proc - [GenerateWZfromOrder]: wrong existing dst document type.');
                }
                if ($existingDocument->IDMagazynu != $WarehouseID) {
                    throw new \Exception('SQL proc - [GenerateWZfromOrder]: wrong existing dst document warehouse.');
                }
            }

            $orderLines = DB::table('dbo.OrderLines as ol')
                ->leftJoin('dbo.VatRates as v', 'v.IDVatRate', '=', 'ol.IDVat')
                ->where('IDOrder', $OrderID)
                ->when($ElementsGUID, function ($query, $ElementsGUID) {
                    return $query->whereIn('IDOrderLine', function ($subQuery) use ($ElementsGUID) {
                        $subQuery->select('IntParam')->from('dbo.Parameters')->where('GUID', $ElementsGUID);
                    });
                })
                ->orderByRaw('COALESCE([DisplayIndex], [IDOrderLine])')
                ->get();

            foreach ($orderLines as $line) {
                $IDItem = $line->IDItem;
                $Amount = $line->Quantity;
                $PriceNet = $line->PriceNet;
                $PriceGross = $line->PriceGross;
                $IDVat = $line->IDVat;
                $Remarks = $line->Remarks;
                $Discount = $line->Discount;
                $FCPriceGross = $line->ForeignCurrencyPriceGross;
                $FCPriceNet = $line->ForeignCurrencyPriceNet;

                if ($IDItem === null) {
                    continue;
                }

                $OnStock = DB::table('stanywdniu')->where('IDTowaru', $IDItem)->value('ilosc') ?? 0;

                $VatRate = DB::table('dbo.VatRates')->where('IDVatRate', $IDVat)->value('Rate');
                $CurrencyRateBaseToForeign = 1;
                if ($FCPriceGross > 0 || $FCPriceNet > 0) {
                    $CurrencyRateBaseToForeign = DB::table('dbo.Orders')->where('IDOrder', $OrderID)->value(DB::raw('dbo.CalculateRate(IDCurrency, IDCurrencyRate, 0)'));
                }

                if (DB::table('Ustawienia')->where('Nazwa', 'UseDiscounts')->value('Wartosc') == 'Nie') {
                    $Discount = 0;
                }
                if ($FCPriceGross > 0 || $FCPriceNet > 0) {
                    $Discount = $Discount * DB::table('dbo.Orders')->where('IDOrder', $OrderID)->value(DB::raw('dbo.CalculateRate(IDCurrency, IDCurrencyRate, 1)'));
                }

                if ($InvAlgGross != $PricesModeGross && $VatRate !== null) {
                    if ($InvAlgGross == 1 && $PricesModeGross == 0) {
                        $PriceNet = $PriceGross / (1 + $VatRate / 100) + $Discount;
                    } else {
                        $PriceGross = $PriceNet * (1 + $VatRate / 100) - $Discount;
                    }
                }

                if (DB::table('Ustawienia')->where('Nazwa', 'PricesMode')->value('Wartosc') == 'Brutto') {
                    if ($PriceGross != 0) {
                        $Price = $PriceGross;
                    } else {
                        $Price = ($PriceNet - $Discount) * (1 + $VatRate / 100);
                    }
                } else {
                    if ($PriceNet != 0) {
                        $Price = ($PriceNet - $Discount);
                    } else {
                        $Price = $PriceGross / (1 + $VatRate / 100);
                    }
                }

                if (($FCPriceGross > 0 || $FCPriceNet > 0) && DB::table('Ustawienia')->where('Nazwa', 'UseCurrenciesOnPZ')->value('Wartosc') == 1) {
                    $FCPrice = $Price * DB::table('dbo.Orders')->where('IDOrder', $OrderID)->value(DB::raw('dbo.CalculateRate(IDCurrency, IDCurrencyRate, 0)'));
                }

                if ($AmountFlag == 1) {
                    $ElementID = DB::table('dbo.ElementRuchuMagazynowego')->insertGetId([
                        'Ilosc' => 0,
                        'Uwagi' => $Remarks,
                        'CenaJednostkowa' => $Price,
                        'IDRuchuMagazynowego' => $DocumentID,
                        'IDTowaru' => $IDItem,
                        'Uzytkownik' => $UserID,
                        'IDOrderLine' => $line->IDOrderLine,
                        'CurrencyPrice' => $FCPrice ?? null,
                    ]);
                } else {
                    $AmountRealized = DB::table('dbo.OrderLines')->where('IDOrderLine', $line->IDOrderLine)->value(DB::raw('dbo.OrderLineRealization(IDOrderLine)'));
                    $Amount -= $AmountRealized;

                    if (($OnStock < $Amount && DB::table('Towar')->where('IDTowaru', $IDItem)->value('Usluga') == 0) || $Amount <= 0) {
                        if ($AmountFlag == 0) {
                            continue;
                        } else {
                            $Amount = $OnStock;
                        }
                    }

                    if (DB::table('Ustawienia')->where('Nazwa', 'SeparateLinesOnWZ')->value('Wartosc') == 'Tak' && DB::table('Towar')->where('IDTowaru', $IDItem)->value('Usluga') != 1) {
                        $IleDoRozdania = $Amount;
                        $Method = DB::table('Ustawienia')->where('Nazwa', 'MetodaLiczeniaWartosci')->value('Wartosc');

                        $kursor = DB::table('StanySzczegolowo')->where('IDtowaru', $IDItem)->orderByRaw("
                        CASE WHEN '$Method' = 'LIFO' THEN Datadokumentu END DESC,
                        CASE WHEN '$Method' = 'FIFO' THEN Datadokumentu END ASC,
                        CASE WHEN '$Method' = 'LOCPRIO' THEN LocationPriority END ASC,
                        CASE WHEN '$Method' = 'EXPIRE' THEN ISNULL(DataWaznosci, 2345678) END ASC
                    ")->get();

                        foreach ($kursor as $row) {
                            $PZElementID = $row->IDElementuPZ;
                            $IleZostalo = $row->Ilosc;
                            $Cena = $row->CenaJednostkowa;

                            $RoznicaElementow = $IleDoRozdania - $IleZostalo;

                            if ($RoznicaElementow > 0) {
                                $ElementID = DB::table('dbo.ElementRuchuMagazynowego')->insertGetId([
                                    'Ilosc' => $IleZostalo,
                                    'Uwagi' => $Remarks,
                                    'CenaJednostkowa' => $Price,
                                    'IDRuchuMagazynowego' => $DocumentID,
                                    'IDTowaru' => $IDItem,
                                    'Uzytkownik' => $UserID,
                                    'IDOrderLine' => $line->IDOrderLine,
                                    'CurrencyPrice' => $FCPrice ?? null,
                                ]);

                                DB::statement('EXEC [dbo].[UtworzZaleznoscPZWZ] ?, ?, ?', [$PZElementID, $ElementID, $IleZostalo]);
                                $IleDoRozdania -= $IleZostalo;
                            } else {
                                $ElementID = DB::table('dbo.ElementRuchuMagazynowego')->insertGetId([
                                    'Ilosc' => $IleDoRozdania,
                                    'Uwagi' => $Remarks,
                                    'CenaJednostkowa' => $Price,
                                    'IDRuchuMagazynowego' => $DocumentID,
                                    'IDTowaru' => $IDItem,
                                    'Uzytkownik' => $UserID,
                                    'IDOrderLine' => $line->IDOrderLine,
                                    'CurrencyPrice' => $FCPrice ?? null,
                                ]);

                                DB::statement('EXEC [dbo].[UtworzZaleznoscPZWZ] ?, ?, ?', [$PZElementID, $ElementID, $IleDoRozdania]);
                                $IleDoRozdania = 0;
                            }

                            if (DB::table('Ustawienia')->where('Nazwa', 'SalesPricesEqualToPurchasePrices')->value('Wartosc') == 1) {
                                DB::table('dbo.ElementRuchuMagazynowego')->where('IDElementuRuchuMagazynowego', $ElementID)->update([
                                    'CenaJednostkowa' => $Cena,
                                    'CurrencyPrice' => $Cena * $CurrencyRateBaseToForeign,
                                ]);
                            }

                            if ($IleDoRozdania <= 0) {
                                break;
                            }
                        }

                        if ($IleDoRozdania > 0) {
                            $ArticleName = DB::table('Towar')->where('IDTowaru', $IDItem)->value('Nazwa');
                            throw new \Exception('Brak wystarczającej ilości towaru "' . $ArticleName . '" do dokonania wydania');
                        }
                    } else {
                        $ElementID = DB::table('dbo.ElementRuchuMagazynowego')->insertGetId([
                            'Ilosc' => $Amount,
                            'Uwagi' => $Remarks,
                            'CenaJednostkowa' => $Price,
                            'IDRuchuMagazynowego' => $DocumentID,
                            'IDTowaru' => $IDItem,
                            'Uzytkownik' => $UserID,
                            'IDOrderLine' => $line->IDOrderLine,
                            'CurrencyPrice' => $FCPrice ?? null,
                        ]);

                        DB::statement('EXEC [dbo].[UpdateDependencies] ?, ?, ?, ?, ?, ?, ?', [$ElementID, $IDItem, $Amount, $DocDate, null, null, $Price]);

                        if (DB::table('Ustawienia')->where('Nazwa', 'SalesPricesEqualToPurchasePrices')->value('Wartosc') == 1) {
                            DB::table('dbo.ElementRuchuMagazynowego')->where('IDElementuRuchuMagazynowego', $ElementID)->update([
                                'CenaJednostkowa' => $Price,
                                'CurrencyPrice' => $Price * $CurrencyRateBaseToForeign,
                            ]);
                        }
                    }
                }

                DB::statement('EXEC CopyDedicatedFields ?, ?, ?, ?, ?, ?', ['OrderLines', 'IDOrderLine', $line->IDOrderLine, 'ElementRuchuMagazynowego', 'IDElementuRuchuMagazynowego', $ElementID]);
            }

            DB::statement('EXEC CopyDedicatedFields ?, ?, ?, ?, ?, ?', ['Orders', 'IDOrder', $OrderID, 'RuchMagazynowy', 'IDRuchuMagazynowego', $DocumentID]);

            if (!DB::table('dbo.DocsRels')->where([
                ['ID1', '=', $DocumentID],
                ['IDType1', '=', $DocumentType],
                ['ID2', '=', $OrderID],
                ['IDType2', '=', $OrderType],
            ])->exists()) {
                DB::table('dbo.DocumentRelations')->insert([
                    'ID1' => $DocumentID,
                    'IDType1' => $DocumentType,
                    'ID2' => $OrderID,
                    'IDType2' => $OrderType,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getPackOrders(Request $request)
    {
        $res = [];
        $o_orders = Collect::query()->where('IDUzytkownika', $request->user->IDUzytkownika)
            ->where('Status', 0)->get();


        $orders = DB::table('Orders as o')
            ->join('RodzajTransportu as rt', 'o.IDTransport', '=', 'rt.IDRodzajuTransportu')
            ->whereIn('o.IDOrder', $o_orders->pluck('IDOrder'))
            ->where('o.IDOrderStatus', 42) // Kompletowanie
            ->select(
                'o.IDOrder',
                'o.IDWarehouse',
                DB::raw('CAST(o._OrdersTempDecimal2 AS INT) as Nr_Baselinker'),
                DB::raw("
                    CASE
                    WHEN rt.IDgroup IS NULL THEN rt.IDRodzajuTransportu
                    ELSE rt.IDgroup
                    END as IDTransport
                "),
                DB::raw("
                    CASE
                    WHEN rt.IDgroup IS NULL THEN rt.Nazwa
                    ELSE (
                        SELECT Nazwa FROM RodzajTransportu WHERE IDRodzajuTransportu = rt.IDgroup
                    )
                    END as transport_name
                "),
                'o.Number as OrderNumber',
                'o._OrdersTempString1 as invoice_number'
            )
            ->orderBy('transport_name')
            ->get();
        $res['orders'] = $orders;

        return response()->json($res);
    }

    public function getOrderPackProducts(Request $request, $IDOrder)
    {
        Carbon::setLocale('pl');
        $showInOrder = $request->showInOrder ?? false;
        $a_pack = [];
        $IDOrder = (int)$IDOrder;
        $collect = Collect::query()->where('IDOrder', $IDOrder)->first();
        $o_ttn = Collect::query()->where('IDOrder', $IDOrder)->where('ttn', '!=', null)->value('ttn');

        if (!$showInOrder) {
            //for get pdf invoice
            $userId = $request->user->IDUzytkownika;
            $order = DB::table('Orders as o')->where('o.IDOrder', $IDOrder)
                ->select(
                    'o.IDWarehouse as IDMagazynu',
                    DB::raw('CAST(o._OrdersTempDecimal2 AS INT) as Nr_Baselinker'),
                    'o._OrdersTempString1 as invoice_number'
                )
                ->first();

            $IDMagazynu = $order->IDMagazynu;
            $symbol = DB::table('Magazyn')->where('IDMagazynu', $IDMagazynu)->value('Symbol');
            $fileName =  str_replace(['/', '\\'], '_', $order->invoice_number);
            $fileName = "pdf/{$symbol}/{$fileName}.pdf";
            //$path = storage_path('app/public/' . $fileName);
            //Download invoice pdf
            if (!Storage::disk('public')->exists($fileName)) {
                $token = $this->getToken($IDMagazynu);
                //Log::info("printing invoice", [$token]);
                \App\Jobs\DownloadInvoicePdf::dispatch($IDMagazynu, $order->invoice_number, $collect->invoice_id, $token);
            }
        }

        $decoded_ttn = [];
        if ($o_ttn) {
            // If $o_ttn is already an array, use it directly; otherwise, decode JSON
            $decoded_ttn = is_array($o_ttn) ? $o_ttn : json_decode($o_ttn, true);
        }
        $o_pack = Collect::query()->where('IDOrder', $IDOrder)->where('pack', '!=', null)->value('pack');
        $decoded_pack = [];
        if ($o_pack) {
            // If $o_pack is already an array, use it directly; otherwise, decode JSON
            $decoded_pack = is_array($o_pack) ? $o_pack : json_decode($o_pack, true);
        }

        $orderLines = DB::table('OrderLines as ol')
            ->join('Towar as t', 'ol.IDItem', '=', 't.IDTowaru')
            ->where('t.Usluga', '!=', 1)
            ->where('ol.IDOrder', $IDOrder)
            ->select(
                DB::raw('MAX(ol.IDOrderLine) as IDOrderLine'),
                DB::raw('SUM(CAST(ol.Quantity AS INT)) as ilosc'),
                't.Nazwa',
                't.KodKreskowy',
                't.IDTowaru',
                't._TowarTempString1 as sku',
                't._TowarTempDecimal1 as Waga',
                't._TowarTempDecimal2 as m3'
            )
            ->groupBy('t.KodKreskowy', 't.Nazwa', 't.Usluga', 't._TowarTempString1', 't._TowarTempDecimal1', 't._TowarTempDecimal2', 't.IDTowaru')
            ->get();

        // Fetch images separately and attach to the result
        $images = DB::table('Towar')
            ->whereIn('IDTowaru', $orderLines->pluck('IDTowaru'))
            ->pluck('Zdjecie', 'IDTowaru');

        $orderLines = $orderLines->map(function ($item) use ($images, $decoded_pack) {
            if (isset($decoded_pack[0]['products']) && count($decoded_pack[0]['products']) > 0) {
                // $decoded_pack[0]['products'] is an array of associative arrays with barcode as key
                $qty = 0;
                foreach ($decoded_pack[0]['products'] as $product) {
                    if (array_key_exists($item->KodKreskowy, $product)) {
                        $qty = $product[$item->KodKreskowy];
                        break;
                    }
                }
                $item->qty = $qty;
            } else {
                $item->qty =  0;
            }
            $img = $images[$item->IDTowaru] ?? null;
            if ($img) {
                $item->img = base64_encode($img);
            } else {
                $item->img = null;
            }
            return $item;
        });


        if (!empty($decoded_ttn)) {

            // Разворачиваем $decoded_ttn и добавляем данные из $orderLines
            $a_pack['ttn'] = [];

            foreach ($decoded_ttn as $ttn_key => $ttn_entry) {
                $ttn_products = [];
                if (isset($ttn_entry['products']) && is_array($ttn_entry['products'])) {
                    foreach ($ttn_entry['products'] as $product) {
                        foreach ($product as $barcode => $qty) {
                            // Найти товар в $orderLines по штрихкоду
                            $orderLine = $orderLines->first(function ($item) use ($barcode) {
                                return $item->KodKreskowy == $barcode;
                            });

                            if ($orderLine) {
                                $ttn_products[] = [
                                    'IDTowaru' => $orderLine->IDTowaru,
                                    'Nazwa' => $orderLine->Nazwa,
                                    'KodKreskowy' => $barcode,
                                    'sku' => $orderLine->sku,
                                    'Waga' => $orderLine->Waga,
                                    'm3' => $orderLine->m3,
                                    'qty' => $qty,
                                    'img' => $orderLine->img ?? null,
                                ];
                            } else {
                                // Если не найден, просто добавить штрихкод и qty
                                $ttn_products[] = [
                                    'IDTowaru' => null,
                                    'Nazwa' => null,
                                    'KodKreskowy' => $barcode,
                                    'sku' => null,
                                    'Waga' => null,
                                    'm3' => null,
                                    'qty' => $qty,
                                    'img' => null,
                                ];
                            }
                        }
                    }
                }
                $a_pack['ttn'][$ttn_key] = [
                    'fields' => $ttn_entry['fields'] ?? null,
                    'packages' => $ttn_entry['packages'] ?? null,
                    'products' => $ttn_products,
                    'lastUpdate' => isset($ttn_entry['lastUpdate']) ? Carbon::parse($ttn_entry['lastUpdate'])->format('Y-m-d H:i') : null,
                ];
            }

            // $decoded_ttn may have arbitrary numeric keys (not just 0,1,2...)
            $ttn_products = [];
            foreach ($decoded_ttn as $ttn_entry) {
                if (isset($ttn_entry['products']) && is_array($ttn_entry['products'])) {
                    foreach ($ttn_entry['products'] as $product) {
                        foreach ($product as $barcode => $qty) {
                            if (!isset($ttn_products[$barcode])) {
                                $ttn_products[$barcode] = 0;
                            }
                            $ttn_products[$barcode] += $qty;
                        }
                    }
                }
            }
            // Now update $orderLines
            $orderLines = $orderLines->map(function ($item) use ($ttn_products) {
                if (isset($ttn_products[$item->KodKreskowy])) {
                    $item->ilosc -= $ttn_products[$item->KodKreskowy];
                    if ($item->ilosc < 0) {
                        $item->ilosc = 0;
                    }
                }
                return $item;
            })->filter(function ($item) {
                return $item->ilosc > 0;
            })->values();
        }

        $a_pack[0] = [
            'lastUpdate' => Carbon::now()->format('Y-m-d H:i:s'),
            'products' => $orderLines,
        ];
        if (!$showInOrder) {
            // If 'date_pack' is null, set it to the current date
            $collect = Collect::query()->where('IDOrder', $IDOrder)->first();
            if ($collect && is_null($collect->date_pack)) {
                $collect->date_pack = Carbon::now();
                $collect->save();
            }
        }

        return $a_pack;
    }

    public function setStatus($order, $status_name)
    {
        $idsatus = DB::table('OrderStatus')->where('Name', $status_name)->value('IDOrderStatus');
        DB::table('Orders')->where('IDOrder', $order['IDOrder'])->update(['IDOrderStatus' => $idsatus]);
        $IDWarehouse = $order['IDWarehouse'];
        $token = $this->getToken($IDWarehouse);
        if (!$token) {
            return response()->json(['error' => 'Token not found'], 404);
        }

        $BL = new \App\Http\Controllers\Api\BaseLinkerController($token);
        $BL->getStatusId($status_name);
        $BL->setOrderStatus([
            'order_id' => $order['Nr_Baselinker'],
            'status_id' =>  $BL->getStatusId($status_name),
        ]);
    }

    public function setOrderPackProducts(Request $request)
    {
        $Order = $request->Order;
        //     "Order": {
        //         "IDOrder": "93184",
        //         "IDWarehouse": "19",
        //         "invoice_number": "57/6/2025",
        //         "Nr_Baselinker": "22068193",
        //         "OrderNumber": "ZO955/25 - SPS"
        $pack = $request->o_pack;
        $allDone = $request->allDone ?? false;
        $pack['0']['lastUpdate'] = Carbon::now()->format('Y-m-d H:i:s');
        $pack = json_encode($pack);
        $o_pack = Collect::query()->where('IDOrder', $Order['IDOrder'])->update(['pack' => $pack]);
        if ($o_pack) {
            if ($allDone) {
                // Save $allDone state in cache for this order
                Cache::put('order_all_done_' . $Order['IDOrder'], $allDone, now()->addDay());
                //$this->setStatus($Order, 'Do wysłania');
            }
            return response()->json(['status' => 'success']);
        }
        return response()->json(['status' => 'error']);
    }

    public function writeTTN(Request $request)
    {
        $Order = $request->Order;
        //     "Order": {
        //         "IDOrder": "93184",
        //         "IDWarehouse": "19",
        //         "invoice_number": "57/6/2025",
        //         "Nr_Baselinker": "22068193",
        //         "OrderNumber": "ZO955/25 - SPS"

        $ttn = $request->o_ttn;
        $nttn =  $request->nttn;

        $ttn[$nttn]['lastUpdate'] = Carbon::now()->format('Y-m-d H:i:s');
        // $ttn = json_encode($ttn);
        // Get existing 'ttn' and 'pack' columns

        $existingTtn = Collect::query()->where('IDOrder', $Order['IDOrder'])->value('ttn');
        if ($existingTtn) {
            // $existingTtnArr = json_decode($existingTtn, true);
            if (is_object($existingTtn)) {
                $existingTtn = (array)$existingTtn;
            }
            if (is_array($existingTtn)) {
                // Use array union to preserve keys from both arrays
                $ttn = $existingTtn + $ttn;
            }
        }
        // Update 'pack' column with JSON string: [{"products":[],"lastUpdate":"YYYY-DD-MM HH:ii:ss"}]
        $packJson = json_encode([[
            'products' => [],
            'lastUpdate' => Carbon::now()->format('Y-m-d H:i:s')
        ]]);

        // Update 'ttn' column
        $ttn = Collect::query()->where('IDOrder', $Order['IDOrder'])->update(['ttn' => $ttn, 'pack' => $packJson]);
        if ($ttn) {
            if (Cache::get('order_all_done_' . $Order['IDOrder'])) {
                $this->setStatus($Order, 'Do wysłania');
            }

            return response()->json(['status' => 'success']);
        }
        Log::error('Error writing TTN for order ID: ' . $Order['IDOrder']);
        return response()->json(['status' => 'error']);
    }

    public function deleteTTN(Request $request)
    {
        $IDOrder = (int)$request->IDOrder;
        $nttn =  $request->nttn;
        $existingTtn = Collect::query()->where('IDOrder', $IDOrder)->value('ttn');
        if ($existingTtn) {

            if (is_object($existingTtn)) {
                $existingTtn = (array)$existingTtn;
            }
            if (is_array($existingTtn)) {
                unset($existingTtn[$nttn]);
                $ttn = json_encode($existingTtn);
                Collect::query()->where('IDOrder', $IDOrder)->update(['ttn' => $ttn]);
            }
        }
        return response()->json(['status' => 'success']);
    }


    public function getRodzajTransportu(Request $request)
    {
        return DB::table('RodzajTransportu')->select("IDRodzajuTransportu", "Nazwa", "IDgroup")->get();
    }

    public function setRodzajTransportu(Request $request)
    {
        $IDgroup = isset($request->IDgroup) ? (int)$request->IDgroup : null;
        $group = $request->group;
        DB::table('RodzajTransportu')->whereIn('IDRodzajuTransportu', $group)->update(['IDgroup' => $IDgroup]);
        return DB::table('RodzajTransportu')->select("IDRodzajuTransportu", "Nazwa", "IDgroup")->get();
    }
}
