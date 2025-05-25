<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function getOrder(Request $request)
    {
        $data = $request->all();
        $res = [];
        if (isset($data['ordername'])) {
            $orderdata = trim($data['ordername']);
            $res['order'] =  collect(DB::select('SELECT [IDOrder] , [Number] , cast ([Created] as date) Created , con.[Nazwa] cName , CAST([_OrdersTempDecimal2] AS INT) as pk , [Remarks] as Uwagi ,[_OrdersTempString7] as Zrodlo ,[_OrdersTempString8] as External_id ,[_OrdersTempString9] as Login_klienta FROM [dbo].[Orders] ord LEFT JOIN [dbo].[Kontrahent] con ON con.[IDKontrahenta] = ord.[IDAccount] WHERE [IDWarehouse] = ' . (int) $data['warehouse'] . ' AND \'' . $orderdata . '\' IN (Number, _OrdersTempString1,_OrdersTempString2, _OrdersTempString3, _OrdersTempString4, CONVERT(NVARCHAR(255), _OrdersTempDecimal1),  CONVERT(NVARCHAR(255), CONVERT(INT, _OrdersTempDecimal2)))'))->first();

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
            $res['order'] = DB::table('Orders')
                ->where('IDOrder', $data['IDOrder'])
                ->where('IDWarehouse', $data['IDWarehouse'])
                ->first();
            $res['products'] = $this->getOrderProducts([$data['IDOrder']]);
            $res['client'] = DB::table('Kontrahent')->where('IDKontrahenta', $res['order']->IDAccount)->first();

            $res['delivery'] = DB::connection('second_mysql')->table('order_details')->where('order_id', $data['IDOrder'])->first();
        }

        return response($res);
    }

    public function getOrderProducts($idOrder)
    {
        $listProducts = DB::table('dbo.OrderLines as ol')
            ->leftJoin('Towar as t', 't.IDTowaru', '=', 'ol.IDItem')
            ->leftJoin('Orders as o', 'o.IDOrder', '=', 'ol.IDOrder')
            ->select('ol.IDItem', DB::raw('CAST(ol.Quantity AS INT) as Quantity'),  'ol.IDOrder', DB::raw('CAST(o._OrdersTempDecimal2 AS INTEGER) as NumberBL'), 'o.IDWarehouse', 't.Nazwa', 't.KodKreskowy as EAN', 't._TowarTempString1 as SKU', 't._TowarTempDecimal1 as Waga', 't._TowarTempDecimal2 as m3')
            ->where('t.Usluga', '!=', 1)
            ->whereIn('ol.IDOrder', $idOrder)

            ->get();

        return $listProducts;
    }
}
