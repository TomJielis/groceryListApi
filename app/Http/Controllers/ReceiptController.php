<?php

namespace App\Http\Controllers;

use App\Services\ReceiptOcrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{
    public function upload(Request $request)
    {
        $fileData = $request->input('file');
        if (!isset($fileData['data'], $fileData['name'])) {
            return response()->json(['error' => 'No file uploaded'], 422);
        }

        $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        $decoded = base64_decode($fileData['data']);
        $filename = uniqid('receipt_', true) . '.' . $ext;
        $relativePath = 'receipts/' . $filename;
        Storage::disk('local')->put($relativePath, $decoded);
        $fullPath = storage_path('app/private/' . $relativePath);

        // Debug: check existence via Storage en via file_exists
        $storageExists = Storage::disk('local')->exists($relativePath);
        $fileExists = file_exists($fullPath);
        \Log::info('Debug receipt upload', [
            'relativePath' => $relativePath,
            'fullPath' => $fullPath,
            'storageExists' => $storageExists,
            'fileExists' => $fileExists,
        ]);

        // Gebruik Storage::disk('local')->exists() voor controle
        if (!$storageExists) {
            \Log::error('Receipt upload: bestand niet gevonden na opslaan', [
                'relativePath' => $relativePath,
                'fullPath' => $fullPath,
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Bestand niet gevonden na uploaden',
                'file_path' => $fullPath,
            ], 500);
        }

        $ocrService = new ReceiptOcrService();
        $productsResult = $ocrService->extractProductsAndPricesFromFile($fullPath);
        ray($productsResult);
        return response()->json([
            'success' => true,
            'file_path' => $fullPath,
            'products' => $productsResult['products'],
            'raw_product_section' => $productsResult['raw_product_section'],
        ]);
    }
}
