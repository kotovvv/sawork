<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
                'rm2.NrDokumentu as RelatedNrDokumentu'
                // DB::raw('COALESCE(DocumentRelations.ID1, 0) as ID1'),
                // DB::raw('COALESCE(rm2.Data, "") as RelatedData'),
                // DB::raw('COALESCE(rm2.NrDokumentu, "") as RelatedNrDokumentu')
            )
            ->leftJoin('dbo.DocumentRelations', function ($join) {
                $join->on('DocumentRelations.ID2', '=', 'rm1.IDRuchuMagazynowego')
                    ->on('DocumentRelations.IDType2', '=', DB::raw('200'));
            })
            ->leftJoin('dbo.RuchMagazynowy as rm2', 'rm2.IDRuchuMagazynowego', '=', 'DocumentRelations.ID1')
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

        // products
        $products = DB::table('dbo.ElementRuchuMagazynowego')->select('Ilosc', 'Uwagi', 'CenaJednostkowa', 'IDTowaru', 'Uzytkownik')->where('IDRuchuMagazynowego', $data['IDRuchuMagazynowego'])->get();
        $productsArray = [];
        foreach ($products as $product) {
            $productsArray[] = [
                'IDRuchuMagazynowego' => $pzID,
                'Ilosc' => $product->Ilosc,
                'Uwagi' => $product->Uwagi,
                'CenaJednostkowa' => $product->CenaJednostkowa,
                'IDTowaru' => $product->IDTowaru,
                'Uzytkownik' => $product->Uzytkownik
            ];
        }

        // Ensure the parent record exists before inserting child records
        if (DB::table('dbo.RuchMagazynowy')->where('IDRuchuMagazynowego', $pzID)->exists()) {
            DB::table('dbo.ElementRuchuMagazynowego')->insert($productsArray);
        } else {
            return response('Parent record does not exist', 400);
        }


        // relation
        $rel = [
            'ID1' => $pzID,
            'IDType1' => 1,
            'ID2' => $data['IDRuchuMagazynowego'],
            'IDType2' => 200
        ];
        DB::table('dbo.DocumentRelations')->insert($rel);
        return response($createPZ['NrDokumentu'] . ' Został już utworzony', 200);
    }


    public function getFiles(Request $request, $IDRuchuMagazynowego)
    {
        $data = $request->all();

        // Define the path to retrieve the files
        $path =  $IDRuchuMagazynowego . '/doc';

        // Get all files from the directory
        $files = Storage::disk('local')->allFiles($path);


        // Check if files exist
        if (empty($files)) {
            return response()->json(['message' => 'No files found'], 404);
        }

        // Prepare file URLs
        $fileUrls = [];
        foreach ($files as $file) {
            $fileUrls[] = Storage::disk('local')->url($file);
        }

        return response()->json(['message' => 'Files retrieved successfully', 'files' => $fileUrls], 200);
    }

    public function uploadFiles(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file',
            'IDRuchuMagazynowego' => 'required|integer'
        ]);

        $files = $request->file('files');
        $IDRuchuMagazynowego = $request->input('IDRuchuMagazynowego');

        // Define the path to save the files
        $path =  $IDRuchuMagazynowego . '/doc';

        $uploadedFiles = [];

        foreach ($files as $file) {
            // Store each file
            $filePath = Storage::disk('local')->putFileAs($path, $file, $file->getClientOriginalName());

            // Check if the file was successfully stored
            if ($filePath) {
                $uploadedFiles[] = $filePath;
            } else {
                return response()->json(['message' => 'File upload failed'], 500);
            }
        }

        return response()->json(['message' => 'Files uploaded successfully', 'paths' => $uploadedFiles], 200);
    }
}
