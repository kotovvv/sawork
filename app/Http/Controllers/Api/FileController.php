<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function getFile($filename)
    {
        // Check if the file exists in the storage
        if (!Storage::disk('public')->exists($filename)) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        // Serve the file
        return Storage::disk('public')->download($filename);
    }
    public function getFiles(Request $request, $IDRuchuMagazynowego)
    {
        $data = $request->all();

        // Define the path to retrieve the files
        $path =  $IDRuchuMagazynowego . '/doc';

        // Get all files from the directory
        $files = Storage::disk('local')->allFiles($path);


        // Check if files exist
        if (empty($files)) {
            return response()->json(['message' => 'No files found'], 404);
        }

        // Prepare file URLs
        $fileUrls = [];
        foreach ($files as $file) {
            $fileUrls[] = Storage::disk('local')->url($file);
        }

        return response()->json(['message' => 'Files retrieved successfully', 'files' => $fileUrls], 200);
    }

    public function uploadFiles(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file',
            'IDRuchuMagazynowego' => 'required|integer'
        ]);

        $files = $request->file('files');
        $IDRuchuMagazynowego = $request->input('IDRuchuMagazynowego');

        // Define the path to save the files
        $path =  $IDRuchuMagazynowego . '/doc';

        $uploadedFiles = [];

        foreach ($files as $file) {
            // Store each file
            $filePath = Storage::disk('local')->putFileAs($path, $file, $file->getClientOriginalName());

            // Check if the file was successfully stored
            if ($filePath) {
                $uploadedFiles[] = $filePath;
            } else {
                return response()->json(['message' => 'File upload failed'], 500);
            }
        }

        return response()->json(['message' => 'Files uploaded successfully', 'paths' => $uploadedFiles], 200);
    }
}
