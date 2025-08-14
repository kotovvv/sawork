<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Collect;

class OrderController extends Controller
{
    public function getOrder(Request $request)
    {
        $data = $request->all();
        $res = [];
        if (isset($data['ordername'])) {
            $orderdata = trim($data['ordername']);
            $res['order'] =  collect(DB::select('SELECT [IDOrder] , [Number] , cast ([Created] as date) Created , con.[Nazwa] cName , CAST([_OrdersTempDecimal2] AS INT) as pk , [Remarks] as Uwagi ,[_OrdersTempString7] as Zrodlo ,[_OrdersTempString8] as External_id ,[_OrdersTempString9] as Login_klienta FROM [dbo].[Orders] ord LEFT JOIN [dbo].[Kontrahent] con ON con.[IDKontrahenta] = ord.[IDAccount] WHERE ord.[IDWarehouse] = ' . (int) $data['warehouse'] . ' AND \'' . $orderdata . '\' IN (Number, _OrdersTempString1,_OrdersTempString2, _OrdersTempString3, _OrdersTempString4, _OrdersTempString10, CONVERT(NVARCHAR(255), _OrdersTempDecimal1),  CONVERT(NVARCHAR(255), CONVERT(INT, _OrdersTempDecimal2)))'))->first();

            if ($res['order']) {
                $res['wz'] = collect(
                    DB::select('SELECT [ID1] as ID FROM [dbo].[DocumentRelations] WHERE [ID2] = ' . $res['order']->IDOrder . ' AND [IDType1] = 2')
                )->first();

                if ($res['wz']) {
                    $res['products'] = DB::select('SELECT tov.[IDTowaru] , [Ilosc] Quantity , [Wydano] Wydano ,[CenaJednostkowa] ,[IDWarehouseLocation] ,tov.[Nazwa] ,tov.[KodKreskowy] ,tov.[_TowarTempString1] ,tov.[Zdjecie] img FROM [dbo].[ElementRuchuMagazynowego] wz LEFT JOIN [dbo].[Towar] tov ON wz.[IDTowaru] = tov.[IDTowaru] WHERE tov.[Usluga] != 1 AND [IDRuchuMagazynowego] = ' . $res['wz']->ID);

                    // check if wzk done
                    $wzk = collect(DB::table('DocumentRelations')->where('ID2', $res['wz']->ID)->where('IDType2', 2)->where('IDType1', 4)->pluck('ID1'))->toArray();

                    if (count($wzk)) {

                        // get returned product
                        $returnedProducts = collect(DB::table('ElementRuchuMagazynowego')->whereIn('IDRuchuMagazynowego',  $wzk)->select('IDTowaru', 'Ilosc')->get())->toArray();
                        foreach ($res['products'] as  $product) {
                            $returnedProduct = array_filter($returnedProducts, function ($item) use ($product) {
                                return $item->IDTowaru == $product->IDTowaru;
                            });
                            if ($returnedProduct) {
                                $returnedProduct = array_shift($returnedProduct);
                                $product->Quantity -= $returnedProduct->Ilosc;
                            }
                        }

                        // return response('Zwrot został już przetworzony dla ' . $orderdata, 202);
                    }

                    $products = [];
                    foreach ($res['products'] as $key => $product) {
                        if ($product->Quantity == 0) continue;
                        $code = $product->IDTowaru;
                        if (!isset($products[$code])) {
                            if ($product->img) {
                                $res['products'][$key]->img =  base64_encode($product->img);
                            }
                            $products[$code] = $product;
                        } else {
                            $products[$code]->Quantity += $product->Quantity;
                        }
                    }
                    $res['products'] = $products;
                }
            }
        }

        if (isset($data['IDOrder']) && isset($data['IDWarehouse'])) {
            $res['wz'] = collect(
                DB::select('SELECT [ID1] as ID FROM [dbo].[DocumentRelations] WHERE [ID2] = ' . $data['IDOrder'] . ' AND [IDType1] = 2')
            )->first();
            if ($res['wz']) {
                $res['wz'] = DB::table('RuchMagazynowy')->where('IDRuchuMagazynowego',  $res['wz']->ID)->select('Data', 'NrDokumentu')->first();
            }

            $res['order'] = DB::table('Orders')
                ->where('IDOrder', $data['IDOrder'])
                ->where('IDWarehouse', $data['IDWarehouse'])
                ->first();
            $res['products'] = $this->getOrderProducts([$data['IDOrder']]);
            $res['client'] = DB::table('Kontrahent')
                ->where('IDKontrahenta', $res['order']->IDAccount)
                ->first();
            $res['statuses'] = DB::table('OrderStatus')->select('Name as title', 'IDOrderStatus as value')->get();

            $delivery = DB::connection('second_mysql')->table('order_details')->where('order_id', $data['IDOrder'])->where('IDWarehouse', $data['IDWarehouse'])->first();
            if ($delivery) {
                $delivery->delivery_country = DB::table('Kraj')->where('kod', $delivery->delivery_country_code)->value('Nazwa');
                $delivery->invoice_country = DB::table('Kraj')->where('kod', $delivery->invoice_country_code)->value('Nazwa');
            }
            if ($delivery) {
                $res['delivery'] = $delivery;
            } else {
                $res['delivery'] = (object)[
                    'order_id' => $data['IDOrder'],
                    'IDWarehouse' => $data['IDWarehouse'],
                    'order_source' => '',
                    'order_source_id' => '',
                    'currency' => '',
                    'payment_method' => '',
                    'payment_method_cod' => '',
                    'payment_done' => '',
                    'delivery_method' => '',
                    'delivery_price' => '',
                    'delivery_package_module' => '',
                    'delivery_package_nr' => '',
                    'delivery_fullname' => '',
                    'delivery_company' => '',
                    'delivery_address' => '',
                    'delivery_city' => '',
                    'delivery_state' => '',
                    'delivery_postcode' => '',
                    'delivery_country_code' => '',
                    'delivery_point_id' => '',
                    'delivery_point_name' => '',
                    'delivery_point_address' => '',
                    'delivery_point_postcode' => '',
                    'delivery_point_city' => '',
                    'invoice_fullname' => '',
                    'invoice_company' => '',
                    'invoice_nip' => '',
                    'invoice_address' => '',
                    'invoice_city' => '',
                    'invoice_state' => '',
                    'invoice_postcode' => '',
                    'invoice_country_code' => '',
                    'delivery_country' => '',
                    'invoice_country' => '',
                ];
            }
        }

        return response($res);
    }

    public function getOrderProducts($IDOrder)
    {
        $orderLines = DB::table('OrderLines as ol')
            ->join('Towar as t', 'ol.IDItem', '=', 't.IDTowaru')
            ->where('ol.IDOrder', $IDOrder)
            ->select(
                'ol.IDOrderLine',
                DB::raw('CAST(ol.PriceGross as decimal(16,2)) as PriceGross'),
                DB::raw('CAST(ol.Quantity AS INT) as ilosc'),
                't.Nazwa',
                't.KodKreskowy',
                't.IDTowaru',
                't.Usluga',
                't._TowarTempString1 as sku',
                't._TowarTempDecimal1 as Waga',
                't._TowarTempDecimal2 as m3'
            )
            ->get();

        // Fetch images separately and attach to the result
        $images = DB::table('Towar')
            ->whereIn('IDTowaru', $orderLines->pluck('IDTowaru'))
            ->pluck('Zdjecie', 'IDTowaru');

        $orderLines = $orderLines->map(function ($item) use ($images) {

            $img = $images[$item->IDTowaru] ?? null;
            if ($img) {
                $item->img = base64_encode($img);
            } else {
                $item->img = null;
            }
            return $item;
        });

        return  $orderLines;
    }

    public function getOrderPack($IDOrder)
    {
        $res = [];
        $pack = Collect::where('IDOrder', $IDOrder)
            ->first();
        if (!$pack) {
            return response()->json(['error' => 'Pack not found'], 404);
        }
        $res['pack'] = $pack;
        //$res['pack']->Date = \Carbon\Carbon::parse($pack->Date)->Format('Y-m-d H:i');
        $Uzytkownik = DB::table('Uzytkownik')
            ->where('IDUzytkownika', $pack->IDUzytkownika)
            ->pluck('NazwaUzytkownika');
        $res['pack']->Uzytkownik = $Uzytkownik->first();

        return $res;
    }

    public function saveDataOrder(Request $request)
    {
        try {
            $data = $request->all();

            // Validate required parameters
            if (!isset($data['IDOrder']) || !isset($data['IDWarehouse']) || !isset($data['section']) || !isset($data['data'])) {
                return response()->json(['error' => 'Missing required parameters'], 400);
            }

            $orderId = $data['IDOrder'];
            $warehouseId = $data['IDWarehouse'];
            $section = $data['section'];
            $updateData = $data['data'];

            // Check if order exists
            $order = DB::table('Orders')
                ->where('IDOrder', $orderId)
                ->where('IDWarehouse', $warehouseId)
                ->first();

            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            if ($section === 'maindata') {
                // Handle main data updates - update multiple tables

                // Update client data (Email, Telefon)
                if (isset($updateData['email']) || isset($updateData['telefon'])) {
                    $clientUpdateData = [];
                    if (isset($updateData['email'])) {
                        $clientUpdateData['Email'] = $updateData['email'];
                    }
                    if (isset($updateData['telefon'])) {
                        $clientUpdateData['Telefon'] = $updateData['telefon'];
                    }

                    if (!empty($clientUpdateData)) {
                        DB::table('Kontrahent')
                            ->where('IDKontrahenta', $order->IDAccount)
                            ->update($clientUpdateData);
                    }
                }

                // Update order data (Źródło, Uwagi, Status)
                $orderUpdateData = [];
                if (isset($updateData['zrodlo'])) {
                    $orderUpdateData['_OrdersTempString7'] = $updateData['zrodlo'];
                }
                if (isset($updateData['remarks'])) {
                    $orderUpdateData['Remarks'] = $updateData['remarks'];
                }
                if (isset($updateData['status'])) {
                    $current_status = DB::table('Orders')
                        ->where('IDOrder', $orderId)
                        ->where('IDWarehouse', $warehouseId)
                        ->value('IDOrderStatus');
                    if ($current_status !== $updateData['status']) {
                        //TODO: Add logic to handle status change, e.g., logging or triggering events
                        /* LogOrder::create([
                                'IDOrder' => $orderId,
                                'IDWarehouse' => $warehouseId,
                                'OldStatus' => $current_status,
                                'NewStatus' => $updateData['status'],
                                'ChangedAt' => now()
                            ]); */
                    }
                    $orderUpdateData['IDOrderStatus'] = $updateData['status'];
                }

                if (!empty($orderUpdateData)) {
                    DB::table('Orders')
                        ->where('IDOrder', $orderId)
                        ->where('IDWarehouse', $warehouseId)
                        ->update($orderUpdateData);
                }

                // Update delivery data (delivery_method, payment_method)
                $deliveryUpdateData = [];
                if (isset($updateData['delivery_method'])) {
                    $deliveryUpdateData['delivery_method'] = $updateData['delivery_method'];
                }
                if (isset($updateData['payment_method'])) {
                    $deliveryUpdateData['payment_method'] = $updateData['payment_method'];
                }

                if (!empty($deliveryUpdateData)) {
                    // Check if delivery record exists in second MySQL database
                    $existingDelivery = DB::connection('second_mysql')
                        ->table('order_details')
                        ->where('order_id', $orderId)
                        ->where('IDWarehouse', $warehouseId)
                        ->first();

                    if ($existingDelivery) {
                        // Update existing record
                        DB::connection('second_mysql')
                            ->table('order_details')
                            ->where('order_id', $orderId)
                            ->where('IDWarehouse', $warehouseId)
                            ->update(array_merge($deliveryUpdateData, ['updated_at' => now()]));
                    } else {
                        // Create new record with all required fields
                        $insertData = array_merge([
                            'order_id' => $orderId,
                            'IDWarehouse' => $warehouseId,
                            'order_source' => '',
                            'order_source_id' => '',
                            'currency' => '',
                            'payment_method' => '',
                            'payment_method_cod' => '',
                            'payment_done' => '',
                            'delivery_method' => '',
                            'delivery_price' => '',
                            'delivery_package_module' => '',
                            'delivery_package_nr' => '',
                            'delivery_fullname' => '',
                            'delivery_company' => '',
                            'delivery_address' => '',
                            'delivery_city' => '',
                            'delivery_state' => '',
                            'delivery_postcode' => '',
                            'delivery_country_code' => '',
                            'delivery_point_id' => '',
                            'delivery_point_name' => '',
                            'delivery_point_address' => '',
                            'delivery_point_postcode' => '',
                            'delivery_point_city' => '',
                            'invoice_fullname' => '',
                            'invoice_company' => '',
                            'invoice_nip' => '',
                            'invoice_address' => '',
                            'invoice_city' => '',
                            'invoice_state' => '',
                            'invoice_postcode' => '',
                            'invoice_country_code' => '',
                            'delivery_country' => '',
                            'invoice_country' => '',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ], $deliveryUpdateData);

                        DB::connection('second_mysql')
                            ->table('order_details')
                            ->insert($insertData);
                    }
                }
            } else {
                // Handle delivery, invoice, pickup sections - update only order_details table

                // Check if delivery record exists in second MySQL database
                $existingDelivery = DB::connection('second_mysql')
                    ->table('order_details')
                    ->where('order_id', $orderId)
                    ->where('IDWarehouse', $warehouseId)
                    ->first();

                if ($existingDelivery) {
                    // Update existing record
                    DB::connection('second_mysql')
                        ->table('order_details')
                        ->where('order_id', $orderId)
                        ->where('IDWarehouse', $warehouseId)
                        ->update(array_merge($updateData, ['updated_at' => now()]));
                } else {
                    // Create new record with all required fields
                    $insertData = array_merge([
                        'order_id' => $orderId,
                        'IDWarehouse' => $warehouseId,
                        'order_source' => '',
                        'order_source_id' => '',
                        'currency' => '',
                        'payment_method' => '',
                        'payment_method_cod' => '',
                        'payment_done' => '',
                        'delivery_method' => '',
                        'delivery_price' => '',
                        'delivery_package_module' => '',
                        'delivery_package_nr' => '',
                        'delivery_fullname' => '',
                        'delivery_company' => '',
                        'delivery_address' => '',
                        'delivery_city' => '',
                        'delivery_state' => '',
                        'delivery_postcode' => '',
                        'delivery_country_code' => '',
                        'delivery_point_id' => '',
                        'delivery_point_name' => '',
                        'delivery_point_address' => '',
                        'delivery_point_postcode' => '',
                        'delivery_point_city' => '',
                        'invoice_fullname' => '',
                        'invoice_company' => '',
                        'invoice_nip' => '',
                        'invoice_address' => '',
                        'invoice_city' => '',
                        'invoice_state' => '',
                        'invoice_postcode' => '',
                        'invoice_country_code' => '',
                        'delivery_country' => '',
                        'invoice_country' => '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $updateData);

                    DB::connection('second_mysql')
                        ->table('order_details')
                        ->insert($insertData);
                }
            }

            // Log the update action (optional)
            Log::info("Order data updated", [
                'order_id' => $orderId,
                'warehouse_id' => $warehouseId,
                'section' => $section,
                'updated_fields' => array_keys($updateData)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order data updated successfully',
                'section' => $section,
                'updated_data' => $updateData
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating order data", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update order data: ' . $e->getMessage()
            ], 500);
        }
    }
}
