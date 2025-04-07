<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;

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
            ->select('o.IDOrder', 'o.IDAccount', 'o.Date', 'o.Number', 'o.Remarks', DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'), 'o.IDWarehouse', 'o.IDUser', 'o.IDTransport', 'rt.Nazwa as transport_name')
            ->where('o.IDOrderType', 15)
            ->where('o.IDOrderStatus', 23)
            ->when($IDsWaiteOrders, function ($query, $IDsWaiteOrders) {
                return $query->whereNotIn('o.IDOrder', $IDsWaiteOrders);
            })
            ->whereNotNull('o._OrdersTempDecimal2') //Nr. Baselinker
            ->whereNotNull('o._OrdersTempString1') //Nr. Faktury BL
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
            ->select('o.IDOrder', 'o.IDAccount', 'o.Date', 'o.Number', 'o.Remarks', DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'), 'o.IDWarehouse', 'o.IDUser', 'o.IDTransport')
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
                $token = Crypt::decryptString($token);
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



            $createdDoc[$IDMagazynu] = null;
            $toLocation['IDWarehouseLocation'] = $this->getUserLocation($IDMagazynu, $request->user->IDUzytkownika);

            foreach ($request->orders as $order) {
                if ($order['IDWarehouse'] != $IDMagazynu) {
                    continue;
                }

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
                }

                $orderOK = true;
                $remarks = $order['Remarks'];
                $products = $this->getListProducts([$order['IDOrder']]);
                $Uwagi = 'User' . $request->user->IDUzytkownika . ' || ' . $order['Remarks'];

                DB::transaction(function () use ($order, $IDMagazynu, $toLocation, $request, $BL, $bl_user_id, &$messages, &$productsERROR, &$orderERROR, &$IDsOrderERROR, &$listProductsOK, &$listOrders, &$createdDoc, $Uwagi, &$orderOK, &$products, $remarks) {
                    $IDsElementuRuchuMagazynowego = [];

                    foreach ($products as $product) {
                        $productsOK = [];
                        $needqty = $product->Quantity;
                        $locations = app('App\Http\Controllers\Api\LocationController')->getProductLocations($product->IDItem);

                        if ($locations) {
                            foreach ($locations as $location) {
                                $result = [];
                                $item = ['IDItem' => $product->IDItem, 'fromLocation' => ['IDWarehouseLocation' => $location['IDWarehouseLocation']], 'selectedWarehause' => $IDMagazynu];
                                $location_ilosc = $location['ilosc'];
                                $qtyToMove = min($needqty, $location_ilosc);
                                $needqty -= $qtyToMove;
                                $productsOK[] = [
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
                                    $remarks += 'Błąd zmiany lokalizacji produktów #BL: ' . $order['NumberBL'] . ' Nazwa: ' . $product->Nazwa . ' EAN: ' . $product->EAN . ' SKU: ' . $product->SKU;
                                    $messages[] = $remarks;
                                    $productsERROR[] = ['IDMagazynu' => $IDMagazynu, 'IDOrder' => $order['IDOrder'], 'NumberBL' => $order['NumberBL'], 'IDItem' => $product['IDItem'], 'qty' => $product['Quantity'], 'Uwagi' => $result->message];
                                    if (env('APP_ENV') != 'local') {
                                        $parameters = [
                                            'order_id' => $order['NumberBL'],
                                            'status_id' => $BL->status_id_Nie_wysylac,
                                        ];
                                        $BL->setOrderStatus($parameters);
                                    }
                                    DB::table('Orders')->where('IDOrder', $order['IDOrder'])->update([
                                        'IDOrderStatus' => 29, //Nie wysyłać
                                        'Remarks' => $remarks
                                    ]);
                                    DB::rollBack();
                                    break;
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
                            $remarks .= 'Niewystarczająca ilość #BL: ' . $order['NumberBL'] . ' Nazwa: ' . $product->Nazwa . ' EAN: ' . $product->EAN . ' SKU: ' . $product->SKU;
                            $messages[] = $remarks;

                            $IDsOrderERROR[] = $order['IDOrder'];
                            if (env('APP_ENV') != 'local') {
                                $parameters = [
                                    'order_id' => $order['NumberBL'],
                                    'status_id' => $BL->status_id_Nie_wysylac,
                                ];
                                $BL->setOrderStatus($parameters);
                            }
                            DB::table('Orders')->where('IDOrder', $order['IDOrder'])->update([
                                'IDOrderStatus' => 29, //Nie wysyłać
                                'Remarks' => $remarks
                            ]);
                            DB::rollBack();

                            break;
                        }
                        $listProductsOK[] = $productsOK;
                    }

                    if ($orderOK) {
                        $listOrders[] = $order;

                        $inserted = DB::table('collect')->insert([
                            'IDUzytkownika' => $request->user->IDUzytkownika,
                            'Date' => Carbon::now(),
                            'IDOrder' => $order['IDOrder'],
                            'status' => 0,
                            'created_doc' => json_encode($createdDoc[$IDMagazynu]),
                            'IDsElementuRuchuMagazynowego' => json_encode($IDsElementuRuchuMagazynowego),
                        ]);

                        if (!$inserted) {
                            throw new \Exception('Error inserting into collect table');
                        }
                        if (env('APP_ENV') != 'local') {
                            $parameters = [
                                'order_id' => $order['NumberBL'],
                                'status_id' => $BL->status_id_Kompletowanie,
                            ];
                            $response = $BL->setOrderStatus($parameters);
                            \Log::info('setOrderStatus response in baselinker:', $response);
                            if (!$response['status'] == 'SUCCESS') {
                                $messages[] = 'Error for order: ' . $order['NumberBL'];
                                throw new \Exception('Error setting order fields in BL');
                            }

                            $parameters = [
                                'order_id' => $order['NumberBL'],
                                'custom_extra_fields' => [$BL->id_exfield_stan => $bl_user_id],
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
                if (!$orderOK) {
                    if (env('APP_ENV') != 'local') {
                        $parameters = [
                            'order_id' => $order['NumberBL'],
                            'status_id' => $BL->status_id_Nie_wysylac,
                        ];
                        $BL->setOrderStatus($parameters);
                    }
                    DB::table('Orders')->where('IDOrder', $order['IDOrder'])->update([
                        'IDOrderStatus' => 29, //Nie wysyłać
                        'Remarks' => $remarks
                    ]);
                }
            }
        }

        $result = [];

        foreach ($listProductsOK as $location) {
            foreach ($location as $item) {
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
        }

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
}
