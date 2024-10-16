<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;

class FileController extends Controller
{
    public function downloadFile(Request $request, $filename)
    {
        // Check if the file exists in the storage
        if (!Storage::disk('public')->exists($filename)) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        // Serve the file
        return Storage::disk('public')->download($filename);
    }

    public function getFile($filename)
    {
        dd($filename);
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
        $path =  $IDRuchuMagazynowego . '/doc/';

        // Get all files from the directory
        $files = Storage::disk('public')->allFiles($path);


        // Check if files exist
        if (empty($files)) {
            return response()->json(['message' => 'No files found'], 404);
        }

        // Prepare file URLs
        $fileUrls = [];
        foreach ($files as $file) {
            $fileUrls[] = [
                'name' => basename($file),
                'url' => Storage::disk('public')->url($file)
            ];
        }

        return response()->json(['message' => 'Files retrieved successfully', 'files' => $fileUrls], 200);
    }

    public function uploadFiles(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file',
            'IDRuchuMagazynowego' => 'required|integer',
            'dir' => 'required|string|max:64'
        ]);

        $files = $request->file('files');
        $snapshots = $request->input('snapshots');
        $IDRuchuMagazynowego = $request->input('IDRuchuMagazynowego');
        $dir = $request->input('dir');
        // Define the path to save the files
        $path =  $IDRuchuMagazynowego . '/' . $dir;
        $uploadedFiles = [];
        if ($snapshots) {
            foreach ($snapshots as $snapshot) {
                // Store each file
                $filename = 'doc-' . time() . '.jpg'; // Generate a unique <filename></filename>

                $imageData = str_replace('data:image/jpeg;base64,', '', $snapshot);

                $imageData = base64_decode($imageData);
                $filePath = Storage::disk('public')->put($path . '/' . $filename, $imageData);
                // Check if the file was successfully stored
                if ($filePath) {
                    // Get the file URL
                    $fileUrl = Storage::disk('public')->url($path . '/' . $filename);

                    // Add file name and URL to the uploaded files array
                    $uploadedFiles[] = [
                        'name' => $filename,
                        'url' => $fileUrl
                    ];
                } else {
                    return response()->json(['message' => 'File upload failed'], 500);
                }
            }
        }

        if ($files) {
            foreach ($files as $file) {
                // Store each file
                $filename = $file->getClientOriginalName(); // Retrieve the original filename
                $filePath = Storage::disk('public')->putFileAs($path, $file, $filename);
                // Check if the file was successfully stored
                if ($filePath) {
                    // Get the file URL
                    $fileUrl = Storage::disk('public')->url($filePath);
                    // Add file name and URL to the uploaded files array
                    $uploadedFiles[] = [
                        'name' => $filename,
                        'url' => $fileUrl
                    ];
                } else {
                    return response()->json(['message' => 'File upload failed'], 500);
                }
            }
        }


        return response()->json(['message' => 'Files uploaded successfully', 'files' => $uploadedFiles], 200);
    }
}
