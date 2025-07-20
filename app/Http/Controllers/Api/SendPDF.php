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

class SendPDF extends Controller
{
    function index()
    {
        $data = [
            'title' => 'Zwrot od odbiorcy ' . date('Y-m-d'),
        ];

        $magazin = [];
        $my = DB::selectOne("SELECT  k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.Kontrahent k WHERE k.IDKontrahenta = 1");

        $docsWZk = DB::table('dbo.RuchMagazynowy as rm')
            ->leftJoin('dbo.Kontrahent as k', 'k.IDKontrahenta', '=', 'rm.IDKontrahenta')
            ->select(
                'rm.IDRuchuMagazynowego',
                'rm.Data',
                'rm.Uwagi',
                'rm.IDMagazynu',
                'rm.NrDokumentu',
                'rm.IDKontrahenta',
                'rm.IDUzytkownika',
                'rm.WartoscDokumentu',
                'k.Nazwa',
                'k.UlicaLokal',
                'k.KodPocztowy',
                'k.Miejscowosc',
                'k.Telefon'
            )
            ->where('rm.NrDokumentu', 'like', 'WZk%')
            ->whereRaw('cast(rm.Data AS date) = cast(GETDATE() AS date)')
            ->whereNotIn('rm.IDRuchuMagazynowego', function ($query) {
                $query->select('IDRuchuMagazynowego')
                    ->from('dbo.EMailLog')
                    ->whereRaw('CAST(Data AS date) = cast(GETDATE() AS date)')
                    ->whereNull('Status');
            })
            ->orderByDesc('rm.IDRuchuMagazynowego')
            ->orderBy('rm.Data', 'asc')
            ->get();

        foreach ($docsWZk as $key => $docWZk) {
            $forpdf = [];
            if ($docWZk->IDUzytkownika != 1) {
                $forpdf['my'] = DB::selectOne("SELECT  k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.Kontrahent k WHERE k.IDKontrahenta = " . $docWZk->IDUzytkownika);
            } else {
                $forpdf['my'] = $my;
            }
            $forpdf['docWZk'] = $docWZk;
            $forpdf['Magazyn'] = DB::selectOne("SELECT Nazwa FROM dbo.Magazyn WHERE IDMagazynu = " . $docWZk->IDMagazynu);
            $email = DB::selectOne("SELECT eMailAddress FROM dbo.EMailMagazyn WHERE IDMagazyn = " . $docWZk->IDMagazynu);
            $forpdf['products'] = DB::select("SELECT t.Nazwa, t.KodKreskowy, erm.Uwagi, erm.Ilosc, erm.CenaJednostkowa, jm.Nazwa ed FROM ElementRuchuMagazynowego erm LEFT JOIN dbo.Towar t ON (erm.IDTowaru = t.IDTowaru) left JOIN JednostkaMiary jm ON (t.IDJednostkiMiary =  jm.IDJednostkiMiary) WHERE IDRuchuMagazynowego = " . $docWZk->IDRuchuMagazynowego);
            //generating pdf with user data
            $pdf = Pdf::loadView('mail', $forpdf);

            $magazin[$forpdf['Magazyn']->Nazwa]['pdfs'][] = $pdf;
            $magazin[$forpdf['Magazyn']->Nazwa]['ndoc'][] = $docWZk->NrDokumentu;
            $magazin[$forpdf['Magazyn']->Nazwa]['email'] = $email;

            //for log email
            $email_log = [
                // 'Data' => date('Y-m-d H:i:s'),
                'IDMagazynu' => $docWZk->IDMagazynu,
                'NrDokumentu' => $docWZk->NrDokumentu,
                'IDRuchuMagazynowego' => $docWZk->IDRuchuMagazynowego

            ];

            DB::table('dbo.EMailLog')->insert($email_log);
            //sleep(10);
        }

        foreach ($magazin as $key => $mag) {
            //send mail to user

            $emails = explode(',', $mag['email']->eMailAddress);

            if ($mag['email']) {
                Mail::send('message', $data, function ($message) use ($mag, $data, $emails) {
                    $message->from(env('MAIL_FROM_ADDRESS'));
                    $message->to($emails);
                    $message->subject($data['title']);
                    foreach ($mag['pdfs'] as $n => $pdf) {
                        $message->attachData($pdf->output(), $mag['ndoc'][$n] . '.pdf'); //attached pdf file
                    }
                });
            }
        }

        return response("Zwroty wysłane: " . count($docsWZk), '200');
    }

    public function downloadPdfs(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['error' => 'No ids provided'], 400);
        }

        $docsWZk = DB::table('dbo.RuchMagazynowy as rm')
            ->leftJoin('dbo.Kontrahent as k', 'k.IDKontrahenta', '=', 'rm.IDKontrahenta')
            ->select(
                'rm.IDRuchuMagazynowego',
                'rm.Data',
                'rm.Uwagi',
                'rm.IDMagazynu',
                'rm.NrDokumentu',
                'rm.IDKontrahenta',
                'rm.IDUzytkownika',
                'rm.WartoscDokumentu',
                'k.Nazwa',
                'k.UlicaLokal',
                'k.KodPocztowy',
                'k.Miejscowosc',
                'k.Telefon'
            )
            ->whereIn('rm.IDRuchuMagazynowego', $ids)

            ->orderByDesc('rm.IDRuchuMagazynowego')
            ->orderBy('rm.Data', 'asc')
            ->get();


        $pdfs = [];
        $my = DB::selectOne("SELECT  k.Nazwa, k.UlicaLokal, k.KodPocztowy, k.Miejscowosc,k.Telefon FROM dbo.Kontrahent k WHERE k.IDKontrahenta = 1");
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
            $pdfs[] = [
                'pdf' => $pdf->output(),
                'name' => str_replace(['\\', ' ', '/'], '_', $docWZk->NrDokumentu) . '.pdf',
            ];
        }

        // Generating PDFs for each document send to browser to download
        if (empty($pdfs)) {
            return response()->json(['error' => 'No PDFs generated'], 404);
        }
        foreach ($pdfs as $pdf) {
            // You can use the response()->stream() to send the PDF directly to the browser
            // return response()->stream(function () use ($pdf) {
            //     echo $pdf['pdf'];
            // }, 200, [
            //     'Content-Type' => 'application/pdf',
            //     'Content-Disposition' => 'attachment; filename="' . $pdf['name'] . '"',
            // ]);
        }

        // If you want to return a zip file with all PDFs
        $zip = new \ZipArchive();
        $zipFileName = 'documents.zip';
        if ($zip->open($zipFileName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json(['error' => 'Could not create zip file'], 500);
        }

        foreach ($pdfs as $pdf) {
            $zip->addFromString($pdf['name'], $pdf['pdf']);
        }
        $zip->close();

        return response()->download($zipFileName)->deleteFileAfterSend(true);
    }
}
