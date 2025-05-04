<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\LogOrder;

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

    public function log_orders(Request $request)
    {
        $data = $request->all();
        $res = [];
        $res['logs'] =  LogOrder::where('IDWarehouse', $data['IDWarehouse'])
            ->when(!empty($data['search']), function ($query) use ($data) {
                return $query->where('number', 'like', '%' . $data['search'] . '%');
            })
            ->when(!empty($data['limit']), function ($query) use ($data) {
                return $query->limit($data['limit'])
                    ->offset(($data['page'] - 1) * $data['limit']);
            })
            ->orderBy('created_at', 'desc')
            ->get();
        $res['count'] = LogOrder::where('IDWarehouse', $data['IDWarehouse'])
            ->when(!empty($data['search']), function ($query) use ($data) {
                return $query->where('number', 'like', '%' . $data['search'] . '%');
            })

            ->count();

        return response()->json($res);
    }
}
