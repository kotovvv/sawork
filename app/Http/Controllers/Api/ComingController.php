<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ComingController extends Controller
{
    public function getDM(Request $request)
    {
        $data = $request->all();
        $IDMagazynu = $data['IDMagazynu'];
        return DB::table('dbo.RuchMagazynowy as rm1')
            ->select(
                'rm1.IDRuchuMagazynowego',
                'rm1.Data',
                'rm1.NrDokumentu',
                'rm1.WartoscDokumentu',
                'DocumentRelations.ID1',
                'rm2.Data as RelatedData',
                'rm2.NrDokumentu as RelatedNrDokumentu',
                'InfoComming.doc',
                'InfoComming.photo',
                'InfoComming.brk',
                'InfoComming.ready'
            )
            ->leftJoin('dbo.DocumentRelations', function ($join) {
                $join->on('DocumentRelations.ID2', '=', 'rm1.IDRuchuMagazynowego')
                    ->on('DocumentRelations.IDType2', '=', DB::raw('200'));
            })
            ->leftJoin('dbo.RuchMagazynowy as rm2', 'rm2.IDRuchuMagazynowego', '=', 'DocumentRelations.ID1')
            ->leftJoin('dbo.InfoComming', 'InfoComming.IDRuchuMagazynowego', '=', 'rm1.IDRuchuMagazynowego')
            ->where('rm1.IDRodzajuRuchuMagazynowego', 200)
            ->where('rm1.IDMagazynu', $IDMagazynu)
            ->orderBy('rm1.Data', 'DESC')
            ->get();
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

        $createPZ['IDRodzajuRuchuMagazynowego'] = 1;
        $createPZ['IDUzytkownika'] = 1;
        $createPZ['Operator'] = 1;
        $createPZ['IDCompany'] = 1;
        $createPZ['IDKontrahenta'] = $IDKontrahenta;
        $createPZ['Uwagi'] = $data['Uwagi'];



        $ndoc = DB::selectOne("SELECT TOP 1 NrDokumentu n FROM dbo.[RuchMagazynowy] WHERE [IDRodzajuRuchuMagazynowego] = '1' AND IDMagazynu = " . $IDMagazynu . " AND year( Utworzono ) = " . date('Y') . " ORDER BY Data DESC");
        preg_match('/^PZ(.*)\/.*/', $ndoc->n, $a_ndoc);
        $createPZ['NrDokumentu'] = 'PZ' . (int) $a_ndoc[1] + 1 . '/' . date('y') . ' - ' . $Symbol;
        // check if NrDokumentu exist in base
        if (DB::table('dbo.RuchMagazynowy')->where('NrDokumentu', $createPZ['NrDokumentu'])->exists()) {
            return response($createPZ['NrDokumentu'] . ' Został już utworzony', 200);
        }

        DB::table('dbo.RuchMagazynowy')->insert($createPZ);
        $pzID = DB::table('dbo.RuchMagazynowy')->where('NrDokumentu', $createPZ['NrDokumentu'])->first()->IDRuchuMagazynowego;

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
        $products = DB::table('dbo.ElementRuchuMagazynowego')->select('Ilosc', 'Uwagi', 'CenaJednostkowa', 'IDTowaru', 'Uzytkownik')->where('IDRuchuMagazynowego', $data['IDRuchuMagazynowego'])->get();
        //$productsArray = [];
        foreach ($products as $product) {
            $productsArray = [
                'IDRuchuMagazynowego' => $pzID,
                'Ilosc' => $product->Ilosc,
                'Uwagi' => $product->Uwagi,
                'CenaJednostkowa' => $product->CenaJednostkowa,
                'IDTowaru' => $product->IDTowaru,
                'Uzytkownik' => $product->Uzytkownik,
                'IDWarehouseLocation' => $IDWarehouseLocation
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
        $res = ['message' => 'Utworzono ' . $createPZ['NrDokumentu'], 'ID1' => $pzID, 'NrDokumentu' => $createPZ['NrDokumentu']];
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

    public function get_PZproducts(Request $request)
    {
        $data = $request->all();
        $IDRuchuMagazynowego = $data['IDRuchuMagazynowego'];
        $IDDM = $data['IDDM'];
        $products =  DB::table('dbo.ElementRuchuMagazynowego as erm')
            ->select(
                'erm.IDElementuRuchuMagazynowego',
                'erm.IDRuchuMagazynowego',
                'erm.IDTowaru',
                DB::raw('CAST(erm.Ilosc as INT) as Ilosc'),

                'erm.IDWarehouseLocation',
                'wl.LocationCode',
                't.Nazwa',
                't.KodKreskowy as KodKreskowy',
                DB::raw('t._TowarTempString1 as sku'),
            )->leftJoin('dbo.Towar as t', 't.IDTowaru', '=', 'erm.IDTowaru')->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)
            ->leftJoin('dbo.WarehouseLocations as wl', 'wl.IDWarehouseLocation', '=', 'erm.IDWarehouseLocation')
            ->get();

        $sumAllProducts = 0;
        foreach ($products as $product) {
            $sumAllProducts += $product->Ilosc;
        }

        $IDWarehouseLocation = $products[0]->IDWarehouseLocation;
        $inLocation = $this->setReady($IDWarehouseLocation, $sumAllProducts, $IDDM);
        foreach ($products as $key => $product) {
            if (isset($inLocation[$product->KodKreskowy])) {
                $products[$key]->inLocation = $inLocation[$product->KodKreskowy];
            } else {
                $products[$key]->inLocation = 0;
            }
        }
        return $products;
    }

    public function setReady($IDWarehouseLocation, $sumAllProducts, $IDRuchuMagazynowego)
    {
        $date = Carbon::now()->format('d/m/Y H:i:s');
        $param = 1; // 0 = Nazvanie, 1 = KodKreskowy
        $query = "SELECT dbo.StockInLocation(?, ?, ?) AS Stock";
        $result = DB::select($query, [$IDWarehouseLocation, $date, $param]);
        $resultString = $result[0]->Stock ?? null;
        $array = [];
        $sum = 0;
        if ($resultString) {
            $pairs = explode(', ', $resultString);
            foreach ($pairs as $pair) {
                list($key, $value) = explode(': ', $pair);
                $array[$key] = (int) $value;
                $sum += (int) $value;
            }
        }

        $percentageMoved = ($sumAllProducts - $sum) * 100 / $sumAllProducts;
        DB::table('dbo.InfoComming')->where('IDRuchuMagazynowego', $IDRuchuMagazynowego)->updateOrInsert(['ready' => $percentageMoved, 'IDRuchuMagazynowego' => $IDRuchuMagazynowego]);
        return $array;
    }
}