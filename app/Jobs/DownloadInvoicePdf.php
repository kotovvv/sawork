<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DownloadInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $symbol;
    protected $IDMagazynu;
    protected $invoice_number;
    protected $invoice_id;
    protected $token;

    public function __construct($IDMagazynu, $invoice_number, $invoice_id, $token)
    {
        $this->symbol = DB::table('Magazyn')->where('IDMagazynu', $IDMagazynu)->value('Symbol');
        $this->IDMagazynu = $IDMagazynu;
        $this->invoice_number = str_replace(['/', '\\'], '_', $invoice_number);
        $this->invoice_id = $invoice_id;
        $this->token = $token;
    }

    public function handle()
    {
        $filename = "pdf/{$this->symbol}/{$this->invoice_number}.pdf";
        if (!Storage::disk('public')->exists($filename)) {
            try {
                $BL = new \App\Http\Controllers\Api\BaseLinkerController($this->token);
                if ($this->invoice_id > 0) {

                    $param = ['invoice_id' => $this->invoice_id];
                    $pdfData = $BL->getInvoiceFile($param);
                    if ($pdfData && isset($pdfData['invoice'])) {
                        // Remove "data:" prefix before decoding
                        $base64 = preg_replace('/^data:/', '', $pdfData['invoice']);
                        Storage::disk('public')->put($filename, base64_decode($base64));
                    }
                }
            } catch (\Exception $e) {
                Log::error('BaseLinker initialization or invoice download failed for invoice ' . $this->invoice_number . ': ' . $e->getMessage());
                // Optionally, you can re-throw the exception if you want the job to fail and be retried
                // throw $e;
            }
        }
    }
}
