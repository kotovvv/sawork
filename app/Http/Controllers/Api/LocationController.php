<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function TowarLocationTipTab(Request $request)
    {
        $data = $request->all();
        $stor = $data['stor'];
        $days = $data['days'];
        $dataMin = Carbon::now()->subDays($days)->format('Ymd');
        $dataMax = Carbon::now()->format('Ymd');
        $idMag = $stor;
        $sql = '';

        DB::table('TowarLocationTipTab')->delete();
        // Выполнение первого блока запросов
        $towarItems = DB::table('Towar')->select('IDTowaru', 'IDMagazynu')->where('IDMagazynu', $idMag)->get();


        $sql = '';
        foreach ($towarItems as $item) {
            $sql = "EXEC TowarLocationTip {$item->IDTowaru}, {$item->IDMagazynu}; ";
            if (!empty($sql)) {
                DB::unprepared($sql);
            }
        }



        // Обновление таблицы TowarLocationTipTab
        $updateSql = "
            UPDATE tlt
            SET tlt.IloscZa5dni = ISNULL(s.SumIlosci, 0)
            FROM TowarLocationTipTab tlt
            JOIN Towar t ON tlt.IDTowaru = t.IDTowaru
            CROSS APPLY (
                SELECT dbo.SumaIlosciTowaruDlaRuchow(2, t.IDTowaru, '$dataMin', '$dataMax', $idMag) AS SumIlosci
            ) AS s
        ";

        DB::unprepared($updateSql);

        // Создание временной таблицы и выполнение SELECT-запроса
        $results = DB::table('TowarLocationTipTab')
            ->select(
                'IDTowaru',
                'KodKreskowy',
                DB::raw('SUM(CASE WHEN TypLocations = 1 THEN Quantity ELSE 0 END) AS Quantity1'),
                DB::raw('SUM(CASE WHEN TypLocations = 2 THEN Quantity ELSE 0 END) AS Quantity2'),
                DB::raw('MAX(IloscZa5dni) AS SoldLast5Days'),
                DB::raw('CASE WHEN SUM(CASE WHEN TypLocations = 1 THEN Quantity ELSE 0 END) < MAX(IloscZa5dni) AND SUM(CASE WHEN TypLocations = 2 THEN Quantity ELSE 0 END) > 0 THEN MAX(IloscZa5dni) - SUM(CASE WHEN TypLocations = 1 THEN Quantity ELSE 0 END) ELSE 0 END AS peremestit')
            )
            ->groupBy('IDTowaru', 'KodKreskowy')
            ->get();

        // Обновление таблицы TowarLocationTipTab на основе временных результатов
        foreach ($results as $result) {
            DB::table('TowarLocationTipTab')
                ->where('IDTowaru', $result->IDTowaru)
                ->where('KodKreskowy', $result->KodKreskowy)
                ->where('TypLocations', 2)
                ->update(['peremestit' => $result->peremestit]);
        }

        unset($tempResults);
        unset($tempResultsCollection);
        return DB::table('TowarLocationTipTab')->get();
    }

    public function getProduct($id)
    {

        $product =   DB::table('dbo.Towar')->where('IDTowaru', $id)->select(
            'IDTowaru',
            'Nazwa',
            'KodKreskowy',
            '_TowarTempString1',
            'Zdjecie',
            DB::raw("0 as qty")
        )->first();

        $product->Zdjecie = base64_encode($product->Zdjecie);
        return $product;
    }
    public function getWarehouseLocations($id)
    {
        return  DB::table('dbo.WarehouseLocations')->where('IDMagazynu', $id)->select(
            'IDWarehouseLocation',
            'LocationCode',
        )->get();
    }

    public function getPZ($idTov, $LocationCode)
    {
        $date = Carbon::now()->format('Ymd');

        $results = DB::table('dbo.ElementRuchuMagazynowego as e')
            ->join('dbo.RuchMagazynowy as r', 'r.IDRuchuMagazynowego', '=', 'e.IDRuchuMagazynowego')
            ->join('dbo.Towar as t', 't.IDTowaru', '=', 'e.IDTowaru')
            ->join('dbo.WarehouseLocations as l', 'l.IDWarehouseLocation', '=', 'e.IDWarehouseLocation')
            ->join(DB::raw('dbo.MostRecentOBDate(?) as BO'), function ($join) use ($date) {
                $join->on('t.IDMagazynu', '=', 'BO.IDMagazynu')
                    ->addBinding($date);
            })
            ->whereRaw('e.ilosc * r.Operator > 0')
            ->where('r.Data', '>=', DB::raw('BO.MinDate'))
            ->where('e.IDTowaru', $idTov)
            ->where('l.LocationCode', $LocationCode)
            ->whereRaw('(e.ilosc - ISNULL(e.Wydano, 0)) > 0')
            ->select(
                'e.IDElementuRuchuMagazynowego',
                'e.IDTowaru',
                'e.ilosc',
                'e.Wydano',
                'e.IDWarehouseLocation',
                'l.LocationCode',
                'r.Operator',
                'r.Data'
            )
            ->get();
        return         $results;
    }
}
