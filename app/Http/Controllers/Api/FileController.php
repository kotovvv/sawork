<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;

class FileController extends Controller
{
    public function deleteFile(Request $request)
    {
        $request->validate([
            'file_url' => 'required|string|max:255'
        ]);

        $file = $request->input('file_url');

        $file = preg_replace('/^.*\/storage\//', '', $file);
        // Check if the file exists in the storage
        if (!Storage::disk('public')->exists($file)) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        // Delete the file
        Storage::disk('public')->delete($file);
        // Extract the path from the file URL
        $path = preg_replace('/^.*\/storage\//', '', dirname($file) . '/');

        // Count the number of files in the folder
        $fileCount = count(Storage::disk('public')->files($path));

        // Update the InfoComming table based on the directory
        if (strpos($path, 'doc') !== false) {
            DB::table('InfoComming')
                ->where('IDRuchuMagazynowego', explode('/', $path)[0])
                ->update(['doc' => $fileCount]);
        } elseif (strpos($path, 'photo') !== false) {
            DB::table('InfoComming')
                ->where('IDRuchuMagazynowego', explode('/', $path)[0])
                ->update(['photo' => $fileCount]);
        }

        return response()->json(['message' => 'File deleted successfully'], 200);
    }


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

        // Check if the file exists in the storage
        if (!Storage::disk('public')->exists($filename)) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        // Serve the file
        return Storage::disk('public')->download($filename);
    }

    public function getFiles(Request $request, $IDRuchuMagazynowego, $folder_name)
    {
        $data = $request->all();

        // Define the path to retrieve the files
        $path =  $IDRuchuMagazynowego . '/' . $folder_name . '/';

        // Get all files from the directory
        $files = Storage::disk('public')->allFiles($path);


        // Check if files exist
        if (empty($files)) {
            return response()->json(['message' => 'No files found'], 404);
        }

        // Prepare file URLs
        $fileUrls = [];
        foreach ($files as $file) {
            $fileType = Storage::disk('public')->mimeType($file);
            $isImage = strpos($fileType, 'image/') === 0;

            $fileUrls[] = [
                'name' => basename($file),
                'url' => Storage::disk('public')->url($file),
                'is_image' => $isImage
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
            foreach ($snapshots as $key => $snapshot) {
                // Store each file
                $filename = 'doc-' . date("Y_m_d_H_i_s") . '_' . $key . '.jpg'; // Generate a unique <filename></filename>

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

        // Count the number of files in the folder
        $fileCount = count(Storage::disk('public')->files($path));

        // Update the InfoComming table based on the directory
        if ($dir === 'doc') {
            DB::table('InfoComming')
                ->updateOrInsert(
                    ['IDRuchuMagazynowego' => $IDRuchuMagazynowego],
                    ['doc' => $fileCount]
                );
        }
        if ($dir === 'photo') {
            DB::table('InfoComming')
                ->updateOrInsert(
                    ['IDRuchuMagazynowego' => $IDRuchuMagazynowego],
                    ['photo' => $fileCount]
                );
        }
        if ($dir === 'zwrot') {
            DB::table('InfoComming')
                ->updateOrInsert(
                    ['IDRuchuMagazynowego' => $IDRuchuMagazynowego],
                    ['photo' => $fileCount]
                );
        }

        return response()->json(['message' => 'Files uploaded successfully', 'files' => $uploadedFiles], 200);
    }
}
