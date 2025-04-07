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
        $dataMin = Carbon::now()->subDays($days)->format('Y-m-d H:i:s');
        $dataMax = Carbon::now()->format('Y-m-d H:i:s');
        $idMag = $stor;
        $sql = '';

        DB::table('TowarLocationTipTab')->delete();
        // Выполнение первого блока запросов
        $towarItems = DB::table('Towar')->select('IDTowaru', 'IDMagazynu')->where('Usluga', 0)->where('KodKreskowy', '!=', '')->where('IDMagazynu', $idMag)->get();

        $sql = '';
        foreach ($towarItems as $item) {
            $sql = "EXEC TowarLocationTip {$item->IDTowaru}, {$item->IDMagazynu}; ";
            if (!empty($sql)) {
                DB::unprepared($sql);
            }
        }

        // Обновление таблицы TowarLocationTipTab
        // $updateSql = "
        //     UPDATE tlt
        //     SET tlt.IloscZa5dni = ISNULL(CAST(CASE WHEN s.SumIlosci > 9999999999999.9999 THEN 9999999999999.9999 ELSE s.SumIlosci END AS decimal(38, 4)), 0)
        //     FROM TowarLocationTipTab tlt
        //     JOIN Towar t ON tlt.IDTowaru = t.IDTowaru and t.Usluga = 0 and t.KodKreskowy != ''
        //     CROSS APPLY (
        //     SELECT dbo.SumaIlosciTowaruDlaRuchow(2, t.IDTowaru, '$dataMin', '$dataMax', $idMag) AS SumIlosci
        //     ) AS s
        //  ";
        $updateSql = "
            UPDATE tlt
	SET tlt.IloscZa5dni =  s.SumIlosci
   	FROM TowarLocationTipTab tlt
      left JOIN Towar t ON tlt.IDTowaru = t.IDTowaru and t.Usluga = 0 and t.KodKreskowy != ''
      CROSS APPLY (
            SELECT sum(Ilosc) AS SumIlosci FROM dbo.ElementRuchuMagazynowego e JOIN dbo.RuchMagazynowy r ON r.IDRuchuMagazynowego = e.IDRuchuMagazynowego AND r.IDMagazynu = $idMag AND r.IDRodzajuRuchuMagazynowego = 2 AND r.Data >= '$dataMin' AND r.Data <= '$dataMax'  WHERE IDTowaru = t.IDTowaru
      ) AS s
         ";


        DB::statement($updateSql);

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
                ->update(['peremestit' => (int) $result->peremestit]);
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
            '_TowarTempString1 as sku',
            'Zdjecie',
            DB::raw("0 as qty")
        )->first();

        $product->Zdjecie = base64_encode($product->Zdjecie);
        return $product;
    }

    public function getWarehouseLocations($id)
    {
        return  DB::table('dbo.WarehouseLocations')->where('IDMagazynu', $id)->get();
    }
    private function getPZ($IDTowaru)
    {
        $DataMax = Carbon::now()->format('Y-m-d H:i:s');
        $IDElementuRuchuMagazynowego = -1;
        $ExcludeElementID = -1;
        $query = "
exec sp_executesql N'Select ID, Sum(Edycja) as Edycja, sum(X.ilosc) as ''qty'', AVG(Cena) as Cena,
X.NumerSerii as ''Numer serii'',
CONVERT(DateTime, CONVERT(Char, X.DataWaznosci, 103), 103) as ''Data ważności'',
X.Idloc as ''IDWarehouseLocation'', X.LocationCode, X.LocationName as ''Nazwa lokalizacji'', X.LocationPriority,
X.NrDokumentu as ''Nr Dokumentu'', X.DataDokumentu As ''Data Dokumentu'',
ElementRuchuMagazynowego.Reserved as ''Rezerwacje dostaw'',
Kontrahent.Nazwa as ''Kontrahent''
, RuchMagazynowy.[_RuchMagazynowyTempBool1] AS ''Niepełnowartościowe'' , RuchMagazynowy.[_RuchMagazynowyTempDecimal1] AS ''Nr. Baselinker'' , RuchMagazynowy.[_RuchMagazynowyTempString2] AS ''Nr. Faktury BL'' , RuchMagazynowy.[_RuchMagazynowyTempString3] AS ''Nr. Korekty BL'' , RuchMagazynowy.[_RuchMagazynowyTempString1] AS ''Nr. Nadania BL'' , RuchMagazynowy.[_RuchMagazynowyTempString4] AS ''Nr. Zwrotny BL'' ,RuchMagazynowy.[_RuchMagazynowyTempLink1] AS ''Link do Korekty BL'' , RuchMagazynowy.[_RuchMagazynowyTempLink2] AS ''Link do Faktury BL'' , RuchMagazynowy.[_RuchMagazynowyTempBool2] AS ''Zweryfikowane'' , RuchMagazynowy.[_RuchMagazynowyTempString5] AS ''Product_Chang'' , RuchMagazynowy.[_RuchMagazynowyTempString6] AS ''Uwagi sprzedawcy'' , RuchMagazynowy.[_RuchMagazynowyTempBool3] AS ''Pieniądze zwrócone'' , RuchMagazynowy.[_RuchMagazynowyTempString7] AS ''Źródło:'' , RuchMagazynowy.[_RuchMagazynowyTempString8] AS ''External_id'' , RuchMagazynowy.[_RuchMagazynowyTempString9] AS ''Login_klienta''
FROM
(
-- stany w danym punkcie czasu:
SELECT IDElementuPZ as ID, CAST(0 as decimal(18,6)) as Edycja,
ilosc, CenaJednostkowa as Cena, --Jednostka, Wartosc as ''Wartość'',
NumerSerii, DataWaznosci, NrDokumentu, DataDokumentu, IDWarehouseLocation as Idloc, LocationCode, LocationName,LocationPriority
FROM StanySzczegolowo(@DataMax)
WHERE IDTowaru = @IDTowaru
AND IDElementuPZ <> @ExcludeElementID
AND LocationCode NOT IN (''Zniszczony'', ''Naprawa'') AND LocationCode NOT LIKE ''User%''
UNION ALL  -- дополнительное существующие элементы
SELECT PZ.IDElementuRuchuMagazynowego ID, PZWZ.ilosc as Edycja,
PZWZ.ilosc,ISNULL(PZ.CenaJednostkowa ,0) as Cena,
PZ.NumerSerii, PZ.DataWaznosci,	RuchPZ.NrDokumentu, RuchPZ.Data,
loc.IDWarehouseLocation as Idloc, isnull(loc.LocationCode,'''') LocationCode, isnull(loc.LocationName,'''') LocationName, loc.Priority as LocationPriority
FROM ZaleznosciPZWZ PZWZ
INNER JOIN ElementRuchuMagazynowego AS WZ ON WZ.IDElementuRuchuMagazynowego = PZWZ.IDElementuWZ
INNER JOIN ElementRuchuMagazynowego AS PZ ON PZ.IDElementuRuchuMagazynowego = PZWZ.IDElementuPZ
INNER JOIN RuchMagazynowy AS RuchWZ ON RuchWZ.IDRuchuMagazynowego = WZ.IDRuchuMagazynowego
INNER JOIN RuchMagazynowy AS RuchPZ ON RuchPZ.IDRuchuMagazynowego = PZ.IDRuchuMagazynowego
INNER JOIN Towar t ON t.IDTowaru = WZ.IDTowaru
LEFT OUTER JOIN WarehouseLocations AS loc ON PZ.IDWarehouseLocation = loc.IDWarehouseLocation
WHERE
t.IdTowaru = @IDTowaru
AND WZ.IDElementuRuchuMagazynowego = @IDElementuRuchuMagazynowego
AND LocationCode NOT IN (''Zniszczony'', ''Naprawa'') AND LocationCode NOT LIKE ''User%''
) X
INNER JOIN [dbo].[ElementRuchuMagazynowego] ON ElementRuchuMagazynowego.IDElementuRuchuMagazynowego = X.ID
INNER JOIN [dbo].[RuchMagazynowy] ON RuchMagazynowy.IDRuchuMagazynowego = ElementRuchuMagazynowego.IDRuchuMagazynowego
LEFT JOIN dbo.Kontrahent ON Kontrahent.IDKontrahenta = RuchMagazynowy.IDKontrahenta
group by ID, X.NumerSerii, X.DataWaznosci ,	X.NrDokumentu , X.DataDokumentu, X.Idloc , X.LocationCode , X.LocationName, X.LocationPriority,
ElementRuchuMagazynowego.Reserved, Kontrahent.Nazwa
, RuchMagazynowy.[_RuchMagazynowyTempBool1] , RuchMagazynowy.[_RuchMagazynowyTempDecimal1] , RuchMagazynowy.[_RuchMagazynowyTempString2] , RuchMagazynowy.[_RuchMagazynowyTempString3] , RuchMagazynowy.[_RuchMagazynowyTempString1] , RuchMagazynowy.[_RuchMagazynowyTempString4] , RuchMagazynowy.[_RuchMagazynowyTempLink1] , RuchMagazynowy.[_RuchMagazynowyTempLink2] , RuchMagazynowy.[_RuchMagazynowyTempBool2] , RuchMagazynowy.[_RuchMagazynowyTempString5] , RuchMagazynowy.[_RuchMagazynowyTempString6] , RuchMagazynowy.[_RuchMagazynowyTempBool3] , RuchMagazynowy.[_RuchMagazynowyTempString7] , RuchMagazynowy.[_RuchMagazynowyTempString8] , RuchMagazynowy.[_RuchMagazynowyTempString9]
ORDER BY LocationPriority asc,''Data Dokumentu'', Edycja desc
',N'@IDTowaru int,@DataMax datetime,@IDElementuRuchuMagazynowego int,@ExcludeElementID int',@IDTowaru=?,@DataMax=?,@IDElementuRuchuMagazynowego=?,@ExcludeElementID=?
";

        $results = DB::select($query, [
            $IDTowaru,
            $DataMax,
            $IDElementuRuchuMagazynowego,
            $ExcludeElementID
        ]);

        return $results;
    }


    public function lastNumber($doc, $symbol)
    {
        $year = Carbon::now()->format('y');
        $pattern =  $doc . '%/' . $year . ' - ' . $symbol;
        $patternIndex = strlen($doc);
        $patternToEndLen = strlen($symbol) + 6; // 6 символов: " - " + год (2 символа) + "/"

        $res = DB::table('RuchMagazynowy')
            ->select(DB::raw('MAX(CAST(SUBSTRING(NrDokumentu, ' . ($patternIndex + 1) . ', LEN(NrDokumentu) - ' . ($patternToEndLen + $patternIndex) . ') AS INT)) as max_number'))
            ->whereRaw('RTRIM(NrDokumentu) LIKE ?', [$pattern])
            ->whereRaw('ISNUMERIC(SUBSTRING(NrDokumentu, ' . ($patternIndex + 1) . ', LEN(NrDokumentu) - ' . ($patternToEndLen + $patternIndex) . ')) <> 0')
            ->value('max_number');

        if ($res === null) {
            return str_replace('%', '1', $pattern);
        }
        return str_replace('%', $res + 1, $pattern);
    }

    public function errorLocation($user, $what, $ip = 0)
    {
        $myLogInfo = date('Y-m-d H:i:s') . ', ' . $ip . ', ' . $user->NazwaUzytkownika . ', ' . $what;
        file_put_contents(
            storage_path() . '/logs/errorLocation.log',
            $myLogInfo . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function doRelokacja(Request $request)
    {
        $resnonse = [];
        $data = $request->all();
        $IDTowaru = $data['IDTowaru'];
        $qty = $data['qty'];
        $fromLocation = $data['fromLocation'];
        $toLocation = $data['toLocation'];
        $idWarehause = $data['selectedWarehause'];
        $createdDoc = $data['createdDoc'];
        if (isset($data['Uwagi'])) {
            $Uwagi = $data['Uwagi'];
        } else {
            $Uwagi = '';
        }
        if (isset($data['IDUzytkownika'])) {
            $IDUzytkownika = $data['IDUzytkownika'];
        } else {
            $IDUzytkownika = 1;
        }
        $pz = [];

        $symbol = DB::table('Magazyn')->where('IDMagazynu', $idWarehause)->value('Symbol');
        // 1. chech if doc cteated
        if ($createdDoc == null) {

            $NrDokumentu = $this->lastNumber('ZL', $symbol);

            $creat_zl = [];

            $creat_zl['IDRodzajuRuchuMagazynowego'] = 27;
            $creat_zl['Data'] = Date('m-d-Y H:i:s');
            $creat_zl['IDMagazynu'] = $idWarehause;
            $creat_zl['NrDokumentu'] = $NrDokumentu;
            $creat_zl['Uwagi'] = $Uwagi;
            $creat_zl['Operator'] = 1;
            $creat_zl['IDCompany'] = 1;
            $creat_zl['IDUzytkownika'] = $IDUzytkownika;
            // $creat_zl['WartoscDokumentu'] = 0; // - в строке в которой отнимаем указываем сумму товаров

            // create doc
            DB::table('dbo.RuchMagazynowy')->insert($creat_zl);
            $resnonse['createdDoc']['idmin'] = DB::table('dbo.RuchMagazynowy')->orderBy('IDRuchuMagazynowego', 'desc')->take(1)->value('IDRuchuMagazynowego');

            DB::table('dbo.RuchMagazynowy')->insert($creat_zl);
            $resnonse['createdDoc']['idpls'] = DB::table('dbo.RuchMagazynowy')->orderBy('IDRuchuMagazynowego', 'desc')->take(1)->value('IDRuchuMagazynowego');

            DB::table('PrzesunieciaMM')->insert(['IDRuchuMagazynowegoZ' => $resnonse['createdDoc']['idmin'], 'IDRuchuMagazynowegoDo' => $resnonse['createdDoc']['idpls']]);
        } else {
            $resnonse['createdDoc'] = $createdDoc;
        }

        //2. ElementRuchuMagazynowego
        $pz = $this->getPZ($IDTowaru);
        $el = [];
        $el['IDTowaru'] = $IDTowaru;
        $qtyToMove = $qty;

        if (empty($pz)) {
            $this->errorLocation($request->user, 'Document ' . $createdDoc . ' No towar ID:' . $IDTowaru . ' qty=' . $qty . ' found from location: ' . $fromLocation['LocationCode'] . ' to location: ' . $toLocation['LocationCode'], $request->ip());
            throw new \Exception('Uwaga! Uwaga! Uwaga! Nie znaleziono identyfikatora towaru ID:' . $IDTowaru . '  z lokalizacji: ' . $fromLocation['LocationCode'] . ' do lokalizacji:' . $toLocation['LocationCode'] . ' qty' . $qty);
        }
        foreach ($pz as $key => $value) {
            $debt = $qtyToMove > $pz[$key]->qty ?  $pz[$key]->qty : $qtyToMove;
            $el['Ilosc'] = -$debt;
            $el['Uwagi'] =  $Uwagi;
            $el['IDRodzic'] = null;
            $el['IDWarehouseLocation'] = null;
            $el['IDRuchuMagazynowego'] = $resnonse['createdDoc']['idmin'];
            $el['CenaJednostkowa'] = $pz[$key]->Cena;

            // Ensure the IDRuchuMagazynowego exists in the RuchMagazynowy table
            $exists = DB::table('dbo.RuchMagazynowy')->where('IDRuchuMagazynowego', $el['IDRuchuMagazynowego'])->exists();

            if ($exists) {
                DB::table('dbo.ElementRuchuMagazynowego')->insert($el);
            } else {
                Log::error('Failed to insert into ElementRuchuMagazynowego: IDRuchuMagazynowego does not exist', ['IDRuchuMagazynowego' => $el['IDRuchuMagazynowego']]);
                throw new \Exception('IDRuchuMagazynowego does not exist in RuchMagazynowy table.');
            }
            $ndocidmin = DB::table('dbo.ElementRuchuMagazynowego')->orderBy('IDElementuRuchuMagazynowego', 'desc')->take(1)->value('IDElementuRuchuMagazynowego');
            $el['Ilosc'] = $debt;
            $el['Uwagi'] =  $Uwagi;
            $el['IDRodzic'] = $ndocidmin;
            $el['IDRuchuMagazynowego'] = $resnonse['createdDoc']['idpls'];
            $el['IDWarehouseLocation'] = $toLocation['IDWarehouseLocation'];
            DB::table('dbo.ElementRuchuMagazynowego')->insert($el);
            $ndocidpls = DB::table('dbo.ElementRuchuMagazynowego')->orderBy('IDElementuRuchuMagazynowego', 'desc')->take(1)->value('IDElementuRuchuMagazynowego');

            $resnonse['IDsElementuRuchuMagazynowego']['min'][] = $ndocidmin;
            $resnonse['IDsElementuRuchuMagazynowego']['pls'][] = $ndocidpls;
            DB::statement('EXEC dbo.UtworzZaleznoscPZWZ @IDElementuPZ = ?, @IDElementuWZ = ?, @Ilosc = ?', [
                $pz[$key]->ID,
                $ndocidmin,
                $debt
            ]);

            $qtyToMove -=  $debt;
            if ($qtyToMove <= 0) break;
        }

        return $resnonse;
    }

    public function updateOrInsertLocation($IDRuchuMagazynowego, $locations)
    {
        // Define the condition to check for existing records
        $condition = ['IDRuchuMagazynowego' => $IDRuchuMagazynowego];
        // Define the data to update or insert
        $data = [
            'locations' => $locations,
            // Add other fields as necessary
        ];

        // Use updateOrInsert to update if exists, or insert if not
        DB::table('InfoComming')->updateOrInsert($condition, $data);
    }

    public function refreshLocations(Request $request)
    {
        $data = $request->all();
        $res = [];
        $IDWarehouse = $data['IDWarehouse'];
        $dateMin = Carbon::parse($data['dateMin'])->setTime(00, 00, 00)->format('m.d.Y H:i:s');
        $dateMax = Carbon::parse($data['dateMax'])->setTime(23, 59, 59)->format('m.d.Y H:i:s');

        // get all WZk for magazin
        $res['allWZk'] = DB::table('RuchMagazynowy')
            ->where('IDMagazynu', $IDWarehouse)
            ->whereBetween('Data', [$dateMin, $dateMax])
            ->pluck('IDRuchuMagazynowego');

        // get locations name
        $loc_name =  (array) DB::table('EMailMagazyn')
            ->where('IDMagazyn', $IDWarehouse)
            ->select('IDLokalizaciiZwrot as ok', 'Zniszczony', 'Naprawa')
            ->first();
        $loc_name = array_flip($loc_name);

        // get locations of products each WZK
        foreach ($res['allWZk'] as $key => $IDRuchuMagazynowego) {
            $locations = [];
            $products = DB::table('ElementRuchuMagazynowego')
                ->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)
                ->get();
            foreach ($products as $key => $product) {
                $locations[] = $loc_name[$product->IDWarehouseLocation] ?? $product->IDWarehouseLocation;
            }
            if (is_array($locations) && count($locations)) {
                $locations = array_unique($locations);
                $this->updateOrInsertLocation($IDRuchuMagazynowego, implode(',', $locations));
            }
        }


        return $res;
    }

    public function getProductLocations($id, $allLocations = 0)
    {
        $IDTowaru = intval($id);
        $results = $this->getPZ($IDTowaru);

        $results = collect($results)->groupBy('LocationCode')->map(function ($row) {
            return [
                'LocationCode' => $row->first()->LocationCode,
                'ilosc' => $row->sum('qty'),
                'IDWarehouseLocation' => $row->first()->IDWarehouseLocation,
                // 'IDRuchuMagazynowego' => $row->first()->ID,
                // 'Data'  => $row->first()->Data Dokumentu,
            ];
        })->values();

        return $results;
    }

    public function getLocationsM3()
    {
        $LocationsM3 = DB::table('LocationsM3')->get();
        return $LocationsM3;
    }

    public function getLocationsTyp()
    {
        $LocationsTypes = DB::table('LocationsTyp')->get();
        return $LocationsTypes;
    }

    public function updateLocationsTyp(Request $request)
    {
        $data = $request->all();
        $IDTyp = $data['IDTyp'];
        $IDWarehouseLocation = $data['IDWarehouseLocation'];
        $TypLocations = $data['TypLocations'];

        DB::table('LocationsTyp')->where('IDTyp', $IDTyp)->update(['TypLocations' => $TypLocations]);
        DB::table('WarehouseLocations')->where('IDWarehouseLocation', $IDWarehouseLocation)->update(['TypLocations' => $TypLocations]);

        return response()->json(['success' => true]);
    }
}
