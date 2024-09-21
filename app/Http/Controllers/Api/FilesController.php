<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FilesController extends Controller
{
    public function savePic(Request $request)
    {
        $snapshots = $request->input('snapshots', []);

        foreach ($snapshots as $index => $snapshot) {
            if ($snapshot['type'] === 'photo') {
                $imageData = $snapshot['data'];
                $imageData = str_replace('data:image/png;base64,', '', $imageData);
                $imageData = str_replace(' ', '+', $imageData);
                $folderName = 'snapshots/' . date('Y-m-d');
                $imageName = $folderName . '/snapshot_' . $index . '.png';

                // Create the folder if it doesn't exist
                if (!Storage::disk('public')->exists($folderName)) {
                    Storage::disk('public')->makeDirectory($folderName);
                }

                Storage::disk('public')->put($imageName, base64_decode($imageData));
            }
        }

        return response()->json(['message' => 'Snapshots saved successfully']);
    }
}