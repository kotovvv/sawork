<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

    private function getPZ($IDTowaru, $LocationCode)
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
            ->where('e.IDTowaru', $IDTowaru)
            ->where('l.LocationCode', $LocationCode)
            ->whereRaw('(e.ilosc - ISNULL(e.Wydano, 0)) > 0')
            ->select(
                'e.IDElementuRuchuMagazynowego',
                'e.IDTowaru',
                'e.ilosc',
                'e.Wydano',
                DB::raw('e.ilosc - ISNULL(e.Wydano, 0) as qty'),
                'e.IDWarehouseLocation',
                'l.LocationCode',
                'r.Operator',
                'r.Data'
            )
            ->get()->toArray();
        return  $results;
    }

    public function doRelokacja(Request $request)
    {
        $data = $request->all();
        $response = [];
        $IDTowaru = $data['IDTowaru'];
        $qty = $data['qty'];
        $LocationCode = $data['fromLocation'];
        $toLocation = $data['toLocation'];
        $selectedWarehause = $data['selectedWarehause'];
        $createdDoc = $data['createdDoc'];
        $pz = [];

        // 1. chech if doc cteated
        if (!$createdDoc) {

            $ndoc = DB::selectOne("SELECT TOP 1 NrDokumentu n FROM dbo.[RuchMagazynowy] WHERE [IDRodzajuRuchuMagazynowego] = '27' AND IDMagazynu = " . $selectedWarehause['IDMagazynu'] . " AND year( Utworzono ) = " . date('Y') . " ORDER BY Data DESC");
            preg_match('/^ZL(.*)\/.*/', $ndoc->n, $a_ndoc);
            $NrDokumentu = 'ZL' . (int) $a_ndoc[1] + 1 . '/' . date('y') . ' - ' . $selectedWarehause["Symbol"];

            $creat_zl = [];

            $creat_zl['IDRodzajuRuchuMagazynowego'] = 27;
            $creat_zl['Data'] = Date('d-m-Y H:i:s');
            $creat_zl['IDMagazynu'] = $selectedWarehause['IDMagazynu'];
            $creat_zl['NrDokumentu'] = $NrDokumentu;
            $creat_zl['Operator'] = 1;
            $creat_zl['IDCompany'] = 1;
            $creat_zl['IDUzytkownika'] = 1;
            // $creat_zl['WartoscDokumentu'] = 0; // - в строке в которой отнимаем указываем сумму товаров

            // create doc
            DB::table('dbo.RuchMagazynowy')->insert($creat_zl);
            $resnonse['createdDoc']['idmin'] = DB::table('dbo.RuchMagazynowy')->orderBy('IDRuchuMagazynowego', 'desc')->take(1)->value('IDRuchuMagazynowego');
            DB::table('dbo.RuchMagazynowy')->insert($creat_zl);
            $resnonse['createdDoc']['idpls'] = DB::table('dbo.RuchMagazynowy')->orderBy('IDRuchuMagazynowego', 'desc')->take(1)->value('IDRuchuMagazynowego');
        } else {
            $resnonse['createdDoc'] = $createdDoc;
        }

        //2. ElementRuchuMagazynowego
        $pz = $this->getPZ($IDTowaru, $LocationCode);
        $el = [];
        $el['IDTowaru'] = $IDTowaru;
        $k = $qty;

        foreach ($pz as $key => $value) {
            $debt = $k > $pz[$key]->qty ?  $pz[$key]->qty : $k;
            $el['Ilosc'] = -$debt;
            $el['IDRodzic'] = null;
            $el['IDWarehouseLocation'] = null;
            $el['IDRuchuMagazynowego'] = $resnonse['createdDoc']['idmin'];
            //$el['CenaJednostkowa'] = 0;
            DB::table('dbo.ElementRuchuMagazynowego')->insert($el);
            $ndocidmin = DB::table('dbo.ElementRuchuMagazynowego')->orderBy('IDElementuRuchuMagazynowego', 'desc')->take(1)->value('IDElementuRuchuMagazynowego');
            $el['Ilosc'] = $debt;
            $el['IDRodzic'] = $ndocidmin;
            $el['IDRuchuMagazynowego'] = $resnonse['createdDoc']['idpls'];
            $el['IDWarehouseLocation'] = $toLocation['IDWarehouseLocation'];
            DB::table('dbo.ElementRuchuMagazynowego')->insert($el);
            $ndocidpls = DB::table('dbo.ElementRuchuMagazynowego')->orderBy('IDElementuRuchuMagazynowego', 'desc')->take(1)->value('IDElementuRuchuMagazynowego');

            DB::statement('EXEC dbo.UtworzZaleznoscPZWZ @IDElementuPZ = ?, @IDElementuWZ = ?, @Ilosc = ?', [
                $pz[$key]->IDElementuRuchuMagazynowego, $ndocidmin, $debt
            ]);
            $k -=  $pz[$key]->qty;
            if ($k <= 0) break;
        }

        return $resnonse;
    }
}
