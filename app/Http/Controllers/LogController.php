<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    public function getUseReportLog(Request $request)
    {
        $path = storage_path('logs/useReport.log');
        if (File::exists($path)) {
            return response()->json(['content' => File::get($path)]);
        } else {
            return response()->json(['error' => 'File not found'], 404);
        }
    }

    public function getUsersLog(Request $request)
    {
        $path = storage_path('logs/Users.log');
        if (File::exists($path)) {
            return response()->json(['content' => File::get($path)]);
        } else {
            return response()->json(['error' => 'File not found'], 404);
        }
    }
}