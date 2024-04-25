<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class MagazynController extends Controller
{
    public function loadMagEmail()
    {
        return DB::select('SELECT [ID],[IDMagazynu] ,[Nazwa] ,[Symbol] ,em.eMailAddress ,em.cod FROM [dbo].[Magazyn] RIGHT JOIN dbo.EMailMagazyn em ON em.IDMagazyn = IDMagazynu');
    }


    public function saveMagEmail(Request $request)
    {
        $data = $request->all();
        $IDMagazyn = $data['IDMagazynu'];
        $eMailAddress = $data['eMailAddress'];
        $cod = $data['cod'];
        if (isset($data['id'])) {
            $res = DB::update('update dbo.EMailMagazyn SET IDMagazyn = ' . $IDMagazyn . ', eMailAddress = \'' . $eMailAddress . '\', cod =\'' . $cod . '\'');
            if ($res) {
                return 0;
            } else {
                return response('Not updated', '404');
            }
        } else {
            $res =  DB::statement('INSERT INTO dbo.EMailMagazyn (IDMagazyn,eMailAddress,cod) VALUES (' . $IDMagazyn . ',\'' . $eMailAddress . '\',\'' . $cod . '\')');
            if ($res) {
                return DB::getPdo()->lastInsertId();
            } else {
                return response('Not inserted', '404');
            }
        }
    }
    public function deleteMagEmail(Request $request)
    {
        $data = $request->all();
        $ID = $data['ID'];

        $res =  DB::table('dbo.EMailMagazyn')->where('ID', $ID)->delete($ID);
        if ($res) {
            return response('Deleted', '200');
        }
    }
}
