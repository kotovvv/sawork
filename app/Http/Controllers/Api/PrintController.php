<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PrintController extends Controller
{
    public function print(Request $request)
    {
        $order = $request->input('order');
        $forTN = $request->input('forTN');
        $printer = 'HP-LaserJet-Pro-Stan1';

        //file_put_contents('/var/www/dev/storage/logs/test.txt', $content);
        exec("lpr -P " . escapeshellarg($printer) . " /var/www/dev/storage/logs/Users.log");
        return response()->json(['status' => 'ok']);
    }

    public function getPrinters()
    {
        // For Linux server
        $output = [];
        exec('lpstat -a', $output);

        // Parsing printer names
        $printers = array_map(function ($line) {
            return strtok($line, ' ');
        }, $output);

        return response()->json(['printers' => $printers]);
    }
}
