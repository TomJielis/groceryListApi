<?php

namespace App\Http\Controllers;

use App\Models\GroceryListItem;
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
        $fullPath = Storage::path($relativePath);
        // Debug: check existence via Storage en via file_exists
        $storageExists = Storage::disk('local')->exists($relativePath);
        $fileExists = file_exists($fullPath);
        $whoami = trim(shell_exec('whoami'));
        $path = getenv('PATH');

        \Log::info('Debug receipt upload', [
            'relativePath' => $relativePath,
            'fullPath' => $fullPath,
            'storageExists' => $storageExists,
            'fileExists' => $fileExists,
            'whoami' => $whoami,
            'PATH' => $path,
        ]);

        if (!$storageExists || !$fileExists) {
            \Log::error('Receipt upload: bestand niet gevonden of niet leesbaar na opslaan', [
                'relativePath' => $relativePath,
                'fullPath' => $fullPath,
                'storageExists' => $storageExists,
                'fileExists' => $fileExists,
                'whoami' => $whoami,
                'PATH' => $path,
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Bestand niet gevonden of niet leesbaar na uploaden',
                'file_path' => $fullPath,
                'whoami' => $whoami,
                'PATH' => $path,
            ], 500);
        }
        $ocrService = new ReceiptOcrService();
        $productsResult = $ocrService->extractProductsAndPricesFromFile($fullPath, true);

        return response()->json([
            'success' => true,
            'file_path' => $fullPath,
            'products' => $productsResult['products'],
            'new_products' => $productsResult['new_products'],
        ]);
    }

    function updateItems(Request $request)
    {
        $listId = $request->input('list_id');
        $newProductions = $request->input('new_products', []);

        // Process new products
        foreach ($newProductions as $product) {
            if (!$product['is_new']) {
                $item = GroceryListItem::where('name', '=', $product['mapped_item_name'])
                    ->where('list_id', '=', $listId)
                    ->first();

            } else {
                $item = new GroceryListItem();
            }


            $item->name = $product['mapped_item_name'];
            $item->quantity = 1;
            $item->list_id = $listId;
            $item->checked = true;
            $item->unit_price = $product['unit_price'];
            $item->save();
        }

        return response()->json(['success' => true]);
    }
}
