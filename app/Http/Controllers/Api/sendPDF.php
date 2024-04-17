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
$data=[
    'username' => 'Кому-то',
    'email' => 'kotovvv@ukr.net',
'title' => 'Zwrot od odbiorcy',
];
        // $data['username'] = 'Кому-то';
        // $data['email'] = 'kotovvv@ukr.net';
        // $data['title'] = 'Zwrot od odbiorcy';
        //generating pdf with user data
        $pdf = Pdf::loadView('mail', $data);
        //send mail to user
        Mail::send('message', $data, function ($message) use ($data, $pdf) {
            $message->from(env('MAIL_FROM_ADDRESS'));
            $message->to($data['email']);
            $message->subject('Test');
            $message->attachData($pdf->output(), 'Test.pdf'); //attached pdf file
        });
    }
}