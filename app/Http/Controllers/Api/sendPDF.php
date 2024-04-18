<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
// for pdf
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable;

class sendPDF extends Controller
{
    function index(Request $request)
    {
        $data = [
            'username' => 'Кому-то',
            'email' => 'kotovvv@ukr.net',
        ];
        $forpdf = [];
        $my = DB::selectOne("SELECT  k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.Kontrahent k WHERE k.IDKontrahenta = 1");

        $docsWZk = DB::select("SELECT TOP 3 rm.IDRuchuMagazynowego, rm.Data, rm.Uwagi , rm.IDMagazynu, rm.NrDokumentu, rm.IDKontrahenta, rm.IDUzytkownika, rm.WartoscDokumentu, k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.RuchMagazynowy rm left JOIN dbo.Kontrahent k ON (k.IDKontrahenta = rm.IDKontrahenta) WHERE NrDokumentu LIKE '%WZk%' ORDER BY IDRuchuMagazynowego DESC ,Data ASC");

        foreach ($docsWZk as $key => $docWZk) {
            if ($docWZk->IDUzytkownika != 1) {
                $forpdf['my'] = DB::selectOne("SELECT  k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.Kontrahent k WHERE k.IDKontrahenta = " . $docWZk->IDUzytkownika);
            } else {
                $forpdf['my'] = $my;
            }
            $forpdf['docWZk'] = $docWZk;
            $forpdf['Magazyn'] = DB::selectOne("SELECT Nazwa FROM dbo.Magazyn WHERE IDMagazynu = " . $docWZk->IDMagazynu);
            $forpdf['products'] = DB::select("SELECT t.Nazwa, t.KodKreskowy, erm.Uwagi, erm.Ilosc, erm.CenaJednostkowa, jm.Nazwa ed FROM ElementRuchuMagazynowego erm LEFT JOIN dbo.Towar t ON (erm.IDTowaru = t.IDTowaru) left JOIN JednostkaMiary jm ON (t.IDJednostkiMiary =  jm.IDJednostkiMiary) WHERE IDRuchuMagazynowego = " . $docWZk->IDRuchuMagazynowego);
            //generating pdf with user data
            $pdf = Pdf::loadView('mail', $forpdf);
            //send mail to user
            $data['title'] = 'Zwrot od odbiorcy nr ' . $docWZk->NrDokumentu;
            Mail::send('message', $data, function ($message) use ($docWZk, $data, $pdf) {
                $message->from(env('MAIL_FROM_ADDRESS'));
                $message->to($data['email']);
                $message->subject($data['title']);
                $message->attachData($pdf->output(), $docWZk->NrDokumentu . '.pdf'); //attached pdf file
            });
            sleep(30);
        }
    }
}
