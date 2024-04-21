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
            'title' => 'Zwrot od odbiorcy ' . date('Y-m-d'),
        ];

        $magazin = [];
        $my = DB::selectOne("SELECT  k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.Kontrahent k WHERE k.IDKontrahenta = 1");

        $docsWZk = DB::select("SELECT top 20 rm.IDRuchuMagazynowego, rm.Data, rm.Uwagi , rm.IDMagazynu, rm.NrDokumentu, rm.IDKontrahenta, rm.IDUzytkownika, rm.WartoscDokumentu, k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.RuchMagazynowy rm left JOIN dbo.Kontrahent k ON (k.IDKontrahenta = rm.IDKontrahenta) WHERE NrDokumentu LIKE '%WZk%' ORDER BY IDRuchuMagazynowego DESC , Data ASC");
        // $docsWZk = DB::select("SELECT  rm.IDRuchuMagazynowego, rm.Data, rm.Uwagi , rm.IDMagazynu, rm.NrDokumentu, rm.IDKontrahenta, rm.IDUzytkownika, rm.WartoscDokumentu, k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.RuchMagazynowy rm left JOIN dbo.Kontrahent k ON (k.IDKontrahenta = rm.IDKontrahenta) WHERE cast(Data AS date) >= DATEADD(day, DATEDIFF(day, 0, GETDATE()), 0) AND NrDokumentu LIKE '%WZk%' ORDER BY IDRuchuMagazynowego DESC , Data ASC");

        foreach ($docsWZk as $key => $docWZk) {
            $forpdf = [];
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

            $magazin[$forpdf['Magazyn']->Nazwa]['pdfs'][] = $pdf;
            $magazin[$forpdf['Magazyn']->Nazwa]['ndoc'][] = $docWZk->NrDokumentu;
            $magazin[$forpdf['Magazyn']->Nazwa]['email'] = env('MAIL_TEST');
            //sleep(10);
        }

        foreach ($magazin as $key => $mag) {
            //send mail to user
            Mail::send('message', $data, function ($message) use ($mag, $data) {
                $message->from(env('MAIL_FROM_ADDRESS'));
                $message->to($mag['email']);
                $message->subject($data['title']);
                foreach ($mag['pdfs'] as $n => $pdf) {
                    $message->attachData($pdf->output(), $mag['ndoc'][$n] . '.pdf'); //attached pdf file
                }
            });
        }


        return response("Zwroty wysłane: " . count($docsWZk), '200');
    }
}