<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class MagazynController extends Controller
{
    public function loadMagEmail()
    {
        return DB::select('SELECT [IDMagazynu] ,[Nazwa] ,[Symbol] ,em.eMailAddress ,em.cod FROM [dbo].[Magazyn] RIGHT JOIN dbo.EMailMagazyn em ON em.IDMagazyn = IDMagazynu');
    }
}
