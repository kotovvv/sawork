<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DownloadInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $IDMagazynu;
    protected $IDOrder;
    protected $invoice_id;
    protected $token;

    public function __construct($IDMagazynu, $IDOrder, $invoice_id, $token)
    {
        $this->IDMagazynu = $IDMagazynu;
        $this->IDOrder = $IDOrder;
        $this->invoice_id = $invoice_id;
        $this->token = $token;
    }

    public function handle()
    {
        $BL = new \App\Http\Controllers\Api\BaseLinkerController($this->token);
        $param = ['invoice_id' => $this->invoice_id];
        $pdfData = $BL->getInvoiceFile($param);
        Log::info('PDF Data:', ['11111111111111111111111111111111111111111']);
        if ($pdfData && isset($pdfData['invoice'])) {
            $dir = public_path("pdf/{$this->IDMagazynu}");
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            // Remove "data:" prefix before decoding
            $base64 = preg_replace('/^data:/', '', $pdfData['invoice']);
            file_put_contents("$dir/{$this->IDOrder}.pdf", base64_decode($base64));
        }
    }
}
