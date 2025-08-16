<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Http\Controllers\Api\MagazynController;

class ComingController extends Controller
{

    public function getDM(Request $request)
    {
        $data = $request->all();
        $IDMagazynu = $data['IDMagazynu'];

        if (isset($request->user)) {
            $magazynController = new MagazynController();
            $magazynController->logUsers($request->user, 'getDM', $request->ip());
        }

        return DB::table('dbo.RuchMagazynowy as rm1')
            ->select(
                'rm1.IDRuchuMagazynowego',
                'rm1.Data',
                'rm1.NrDokumentu',
                'rm1.WartoscDokumentu',
                'rm1._RuchMagazynowyTempBool1 as tranzit_warehouse',
                'rm1._RuchMagazynowyTempString8 as External_id',
                'DocumentRelations.ID1',
                'rm2.Data as RelatedData',
                'rm2.NrDokumentu as RelatedNrDokumentu',
                'rm2.Uwagi',
                'InfoComming.doc',
                'InfoComming.photo',
                'InfoComming.brk',
                'InfoComming.ready',
                DB::raw('MAX(CASE WHEN t._TowarTempBool1 IS NULL OR t._TowarTempBool1 = \'\' THEN 0 WHEN t._TowarTempBool1 IN (\'1\', 1, \'true\', \'TRUE\', \'True\') THEN 1 ELSE 0 END) as noBaselink'),
            )
            ->leftJoin('dbo.DocumentRelations', function ($join) {
                $join->on('DocumentRelations.ID2', '=', 'rm1.IDRuchuMagazynowego')
                    ->on('DocumentRelations.IDType2', '=', DB::raw('200'));
            })
            ->leftJoin('dbo.RuchMagazynowy as rm2', 'rm2.IDRuchuMagazynowego', '=', 'DocumentRelations.ID1')
            ->leftJoin('dbo.InfoComming', 'InfoComming.IDRuchuMagazynowego', '=', 'rm1.IDRuchuMagazynowego')
            ->leftJoin('dbo.ElementRuchuMagazynowego as erm', 'erm.IDRuchuMagazynowego', '=', 'DocumentRelations.ID1')
            ->leftJoin('dbo.Towar as t', 't.IDTowaru', '=', 'erm.IDTowaru')
            ->where('rm1.IDRodzajuRuchuMagazynowego', 200)
            ->where('rm1.IDMagazynu', $IDMagazynu)
            ->groupBy(
                'rm1.IDRuchuMagazynowego',
                'rm1.Data',
                'rm1.NrDokumentu',
                'rm1.WartoscDokumentu',
                'DocumentRelations.ID1',
                'rm2.Data',
                'rm2.NrDokumentu',
                'rm2.Uwagi',
                'InfoComming.doc',
                'InfoComming.photo',
                'InfoComming.brk',
                'InfoComming.ready',
                'rm1._RuchMagazynowyTempBool1',
                'rm1._RuchMagazynowyTempString8',
            )
            ->orderBy('rm1.Data', 'DESC')
            ->get();
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

    public function createPZ(Request $request)
    {
        $data = $request->all();
        $IDMagazynu = $data['IDMagazynu'];
        $Symbol = DB::table('dbo.Magazyn')->where('IDMagazynu', $IDMagazynu)->first()->Symbol;
        $IDKontrahenta = DB::table('dbo.EMailMagazyn')->where('IDMagazyn', $IDMagazynu)->where('IDKontrahenta', '>', 0)->first()->IDKontrahenta;

        // create PZ
        $createPZ = [];
        $createPZ['IDMagazynu'] = $IDMagazynu;
        $createPZ['Data'] = date('Y/m/d H:i:s');
        $createPZ['Uwagi'] = isset($data['Uwagi']) ? $data['Uwagi'] : '';
        $createPZ['IDRodzajuRuchuMagazynowego'] = 1;
        $createPZ['IDUzytkownika'] = 1;
        $createPZ['Operator'] = 1;
        $createPZ['IDCompany'] = 1;
        $createPZ['IDKontrahenta'] = $IDKontrahenta;
        // $createPZ['Uwagi'] = $data['Uwagi'];



        // $ndoc = DB::selectOne("SELECT TOP 1 NrDokumentu as n FROM dbo.[RuchMagazynowy] WHERE [IDRodzajuRuchuMagazynowego] = '1' AND IDMagazynu = " . $IDMagazynu . " AND year( Utworzono ) = " . date('Y') . " ORDER BY Data DESC");
        // preg_match('/^PZ(.*)\/.*/', $ndoc->n, $a_ndoc);
        // $createPZ['NrDokumentu'] = 'PZ' . (int) $a_ndoc[1] + 1 . '/' . date('y') . ' - ' . $Symbol;

        $documentNumber = $this->lastNumber('PZ', $Symbol);
        $createPZ['NrDokumentu'] = $documentNumber;

        // check if NrDokumentu exist in base
        if (DB::table('dbo.RuchMagazynowy')->where('NrDokumentu', $documentNumber)->exists()) {
            return response($documentNumber . ' Został już utworzony', 200);
        }

        DB::table('dbo.RuchMagazynowy')->insert($createPZ);
        $pzRecord = DB::table('dbo.RuchMagazynowy')->where('NrDokumentu', $documentNumber)->first();
        if ($pzRecord === null) {
            return response('Parent record does not exist', 400);
        }
        $pzID = $pzRecord->IDRuchuMagazynowego;

        $LocationCode = 'prihod' . date('dmy');
        $location = [
            'LocationCode' => $LocationCode,
            'IDMagazynu' => $IDMagazynu,
            'IsArchive' => 1,
            'Priority' => 100000,
            'TypLocations' => 3
        ];
        DB::table('dbo.WarehouseLocations')->insert($location);
        $IDWarehouseLocation = DB::table('dbo.WarehouseLocations')->where('LocationCode', $LocationCode)->where('IDMagazynu', $IDMagazynu)->first()->IDWarehouseLocation;

        // products
        $products = DB::table('dbo.ElementRuchuMagazynowego')->select('Ilosc', 'Uwagi', 'CenaJednostkowa', 'IDTowaru', 'Uzytkownik', 'NumerSerii')->where('IDRuchuMagazynowego', $data['IDRuchuMagazynowego'])->get();
        //$productsArray = [];
        foreach ($products as $product) {
            $productsArray = [
                'IDRuchuMagazynowego' => $pzID,
                'Ilosc' => $product->Ilosc,
                'Uwagi' => $product->Uwagi,
                'CenaJednostkowa' => $product->CenaJednostkowa,
                'IDTowaru' => $product->IDTowaru,
                'Uzytkownik' => $product->Uzytkownik,
                'IDWarehouseLocation' => $IDWarehouseLocation,
                'NumerSerii' => $product->NumerSerii ? $product->NumerSerii : null
            ];
            DB::table('dbo.ElementRuchuMagazynowego')->insert($productsArray);
        }

        // Ensure the parent record exists before inserting child records
        // if ($pzID) {
        //     DB::table('dbo.ElementRuchuMagazynowego')->insert($productsArray);

        // } else {
        //     return response('Parent record does not exist', 400);
        // }


        // relation
        $rel = [
            'ID1' => $pzID,
            'IDType1' => 1,
            'ID2' => $data['IDRuchuMagazynowego'],
            'IDType2' => 200
        ];
        DB::table('dbo.DocumentRelations')->insert($rel);
        $res = ['message' => 'Utworzono ' . $documentNumber, 'ID1' => $pzID, 'NrDokumentu' => $documentNumber];
        return response($res, 200);
    }

    public function setBrack(Request $request)
    {
        $data = $request->all();
        $IDRuchuMagazynowego = $data['IDRuchuMagazynowego'];
        $brk = $data['brk'];
        DB::table('dbo.InfoComming')->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)->update(['brk' => $brk]);
        return response('Zaktualizowano', 200);
    }

    public function getSetPZ(Request $request)
    {
        $data = $request->all();
        $IDRuchuMagazynowego = $data['IDRuchuMagazynowego'];

        if (isset($data['Uwagi'])) {
            DB::table('dbo.RuchMagazynowy')->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)->update(['Uwagi' => $data['Uwagi']]);
        }
        $docPZ = DB::table('dbo.RuchMagazynowy')->select('IDRuchuMagazynowego', 'Data', 'Uwagi', 'IDMagazynu', 'NrDokumentu', 'WartoscDokumentu', 'NumerSerii')->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)->first();
        return response()->json($docPZ, 200);
    }

    public function setPhoto(Request $request)
    {
        $data = $request->all();
        $IDRuchuMagazynowego = $data['IDRuchuMagazynowego'];
        $photo = $data['photo'];
        $photo = str_replace('data:image/jpeg;base64,', '', $photo);
        $photo = str_replace(' ', '+', $photo);
        $photo = base64_decode($photo);
        $path = 'public/photos/' . $IDRuchuMagazynowego . '.jpg';
        Storage::put($path, $photo);
        DB::table('dbo.InfoComming')->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)->update(['photo' => $path]);
        return response('Zaktualizowano', 200);
    }

    public function get_PZproducts(Request $request)
    {
        $res = ['products' => [], 'productsDM' => []];
        $data = $request->all();
        $IDRuchuMagazynowego = $data['IDRuchuMagazynowego'];
        $IDDM = $data['IDDM'];
        if ($IDRuchuMagazynowego === null) {
            $IDRuchuMagazynowego = $IDDM;
            $res['productsDM'] =  DB::table('dbo.ElementRuchuMagazynowego as erm')
                ->select(
                    'erm.IDElementuRuchuMagazynowego',
                    'erm.IDRuchuMagazynowego',
                    'erm.IDTowaru',
                    'erm.NumerSerii',
                    DB::raw('CAST(erm.Ilosc as INT) as Ilosc'),

                    'erm.IDWarehouseLocation',
                    'wl.LocationCode',
                    't.Nazwa',
                    't.KodKreskowy as KodKreskowy',
                    't._TowarTempBool1 as noBaselink',
                    DB::raw('t._TowarTempString1 as sku'),
                )
                ->leftJoin('dbo.Towar as t', 't.IDTowaru', '=', 'erm.IDTowaru')
                ->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)
                ->where('t.Usluga', '!=', 1)
                ->leftJoin('dbo.WarehouseLocations as wl', 'wl.IDWarehouseLocation', '=', 'erm.IDWarehouseLocation')
                ->get();
        } else {
            $res['productsDM'] =  DB::table('dbo.ElementRuchuMagazynowego as erm')
                ->select(
                    'erm.IDElementuRuchuMagazynowego',
                    'erm.IDRuchuMagazynowego',
                    'erm.IDTowaru',
                    'erm.NumerSerii',
                    DB::raw('CAST(erm.Ilosc as INT) as Ilosc'),

                    'erm.IDWarehouseLocation',
                    'wl.LocationCode',
                    't.Nazwa',
                    't.KodKreskowy as KodKreskowy',
                    't._TowarTempBool1 as noBaselink',
                    DB::raw('t._TowarTempString1 as sku'),
                )
                ->leftJoin('dbo.Towar as t', 't.IDTowaru', '=', 'erm.IDTowaru')
                ->where('IDRuchuMagazynowego', $IDDM)
                ->where('t.Usluga', '!=', 1)
                ->leftJoin('dbo.WarehouseLocations as wl', 'wl.IDWarehouseLocation', '=', 'erm.IDWarehouseLocation')
                ->get();
            $products =  DB::table('dbo.ElementRuchuMagazynowego as erm')
                ->select(
                    'erm.IDElementuRuchuMagazynowego',
                    'erm.IDRuchuMagazynowego',
                    'erm.IDTowaru',
                    'erm.NumerSerii',
                    DB::raw('CAST(erm.Ilosc as INT) as Ilosc'),

                    'erm.IDWarehouseLocation',
                    'wl.LocationCode',
                    't.Nazwa',
                    't.KodKreskowy as KodKreskowy',
                    't._TowarTempBool1 as noBaselink',
                    DB::raw('t._TowarTempString1 as sku'),
                )
                ->leftJoin('dbo.Towar as t', 't.IDTowaru', '=', 'erm.IDTowaru')
                ->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)
                ->where('t.Usluga', '!=', 1)
                ->leftJoin('dbo.WarehouseLocations as wl', 'wl.IDWarehouseLocation', '=', 'erm.IDWarehouseLocation')
                ->get();

            $sumAllProducts = 0;
            foreach ($products as $product) {
                $sumAllProducts += $product->Ilosc;
            }

            $sum = 0;
            foreach ($products as $key => $product) {
                $ilosc = $this->getQuantityForId($product->IDElementuRuchuMagazynowego, $product->IDTowaru, $product->IDRuchuMagazynowego);
                if ($ilosc) {
                    $products[$key]->inLocation = (int)$ilosc->Ilosc ?? 0;
                    $sum += $ilosc->Ilosc ?? 0;
                } else {
                    $products[$key]->inLocation = 0;
                }
            }
            $ready = ($sumAllProducts - $sum) * 100 / $sumAllProducts;
            $this->setReady($IDDM, $ready);
            $res['products'] = $products;
        }

        return $res;
    }
    private function getQuantityForId($id, $idTowaru, $idElementuRuchuMagazynowego)
    {
        $date = Carbon::now()->format('Y/m/d H:i:s');
        return DB::selectOne("
        SELECT SUM(X.ilosc) as Ilosc FROM (
            SELECT IDElementuPZ as ID, ilosc
            FROM StanySzczegolowo(?)
            WHERE IDTowaru = ? AND IDElementuPZ = ?

            UNION ALL

            SELECT PZ.IDElementuRuchuMagazynowego as ID, PZWZ.ilosc
            FROM ZaleznosciPZWZ PZWZ
            INNER JOIN ElementRuchuMagazynowego AS WZ ON WZ.IDElementuRuchuMagazynowego = PZWZ.IDElementuWZ
            INNER JOIN ElementRuchuMagazynowego AS PZ ON PZ.IDElementuRuchuMagazynowego = PZWZ.IDElementuPZ
            INNER JOIN Towar t ON t.IDTowaru = WZ.IDTowaru
            WHERE t.IdTowaru = ? AND WZ.IDElementuRuchuMagazynowego = ?
                  AND PZ.IDElementuRuchuMagazynowego = ?
        ) X
    ", [$date, $idTowaru, $id, $idTowaru, $idElementuRuchuMagazynowego, $id]);
    }


    // private function getProductsInLocation($IDWarehouseLocation)
    // {
    //     $date = Carbon::now()->format('Y/m/d H:i:s');

    //     $param = 1; // 0 = Nazvanie, 1 = KodKreskowy
    //     $query = "SELECT dbo.StockInLocation(?, ?, ?) AS Stock";
    //     $result = DB::select($query, [$IDWarehouseLocation, $date, $param]);
    //     $resultString = $result[0]->Stock ?? null;
    //     $array = [];

    //     if ($resultString) {
    //         $pairs = explode(', ', $resultString);
    //         foreach ($pairs as $pair) {
    //             list($key, $value) = explode(': ', $pair);
    //             $array[$key] = (int) $value;
    //         }
    //     }
    //     return $array;
    // }

    private function setReady($IDRuchuMagazynowego, $ready)
    {
        if (DB::table('dbo.InfoComming')->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)->exists()) {
            DB::table('dbo.InfoComming')->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)->update(['ready' => $ready, 'IDRuchuMagazynowego' => $IDRuchuMagazynowego]);
        } else {
            DB::table('dbo.InfoComming')->insert(['ready' => $ready, 'IDRuchuMagazynowego' => $IDRuchuMagazynowego]);
        }
    }
}
