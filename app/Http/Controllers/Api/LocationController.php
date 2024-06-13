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
        $sql = '';
        // 1 ==========================
        DB::table('TowarLocationTipTab')->delete();
        $prods = DB::table('Towar')->select('IDTowaru', 'IDMagazynu')->get();

        foreach ($prods as $prod) {
            $prodId = $prod->IDTowaru;
            $idMag = $prod->IDMagazynu;
            $sql .= "EXEC TowarLocationTip {$prodId}, {$idMag}; ";
        }

        if (!empty($sql)) {
            DB::unprepared($sql);
        }

        // 2 ==========================
        $dataMin = Carbon::now()->subDays($days)->format('Ymd');
        $dataMax = Carbon::now()->format('Ymd');
        $idMag = $stor;

        // Получение данных из таблицы Towar
        $towarRecords = DB::table('Towar')->get(['IDTowaru']);

        // Итерация по результатам и обновление записей
        foreach ($towarRecords as $towar) {
            $sumIlosci = DB::selectOne(
                "SELECT dbo.SumaIlosciTowaruDlaRuchow(?, ?, ?, ?, ?) AS SumIlosci",
                [2, $towar->IDTowaru, $dataMin, $dataMax, $idMag]
            )->SumIlosci;

            // Обновление записи в таблице TowarLocationTipTab
            DB::table('TowarLocationTipTab')
                ->where('IDTowaru', $towar->IDTowaru)
                ->update(['IloscZa5dni' => $sumIlosci ?? 0]);
        }

        // 1. Выполнение агрегирующего запроса и создание временной таблицы
        $tempResults = DB::table('TowarLocationTipTab')
            ->select(
                'IDTowaru',
                'KodKreskowy',
                DB::raw("SUM(CASE WHEN TypLocations = '1' THEN Quantity ELSE 0 END) AS Quantity1"),
                DB::raw("SUM(CASE WHEN TypLocations = '2' THEN Quantity ELSE 0 END) AS Quantity2"),
                DB::raw("MAX(IloscZa5dni) AS SoldLast5Days")
            )
            ->groupBy('IDTowaru', 'KodKreskowy')
            ->get()
            ->map(function ($item) {
                $item->peremestit = ($item->Quantity1 < $item->SoldLast5Days && $item->Quantity2 > 0)
                    ? $item->SoldLast5Days - $item->Quantity1
                    : 0;
                return (array) $item; // Преобразование в массив
            });

        // Создание временной таблицы в виде коллекции
        $tempResultsCollection = collect($tempResults);

        // 2. Обновление основной таблицы на основе временной таблицы
        foreach ($tempResultsCollection as $tempResult) {
            DB::table('TowarLocationTipTab')
                ->where('IDTowaru', $tempResult['IDTowaru'])
                ->where('KodKreskowy', $tempResult['KodKreskowy'])
                ->where('TypLocations', '2')
                ->update(['peremestit' => $tempResult['peremestit']]);
        }

        unset($tempResults);
        unset($tempResultsCollection);
        return DB::table('TowarLocationTipTab')->get();
    }
}
