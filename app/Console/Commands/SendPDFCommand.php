<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\SendPDF;

class SendPDFCommand extends Command
{
    protected $signature = 'pdf:send';

    protected $description = 'Send PDF via API';

    public function handle()
    {
        $sendPDFController = new SendPDF();
        $sendPDFController->index();
    }
}