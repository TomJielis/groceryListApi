<?php

namespace App\Services;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ReceiptOcrService
{
    /**
     * Extract products and prices from receipt image
     *
     * @param string $filePath Full path to the receipt image file
     * @param bool $raw Whether to skip preprocessing
     * @return array Array containing products and debug info
     */
    public function extractProductsAndPricesFromFile($filePath, $raw = false)
    {
        if (!file_exists($filePath)) {
            return [
                'products' => ['error' => 'Bestand niet gevonden: ' . $filePath],
                'debug_raw_ocr' => null,
            ];
        }

        // Perform OCR
        $text = $this->performOCR($filePath);

        if (isset($text['error'])) {
            return [
                'products' => ['error' => $text['error']],
                'debug_raw_ocr' => $text['raw_text'] ?? null,
            ];
        }

        $ocrText = $text['text'];

        if (empty(trim($ocrText))) {
            return [
                'products' => ['error' => 'Geen tekst herkend uit afbeelding (lege OCR-output)'],
                'debug_raw_ocr' => $ocrText,
            ];
        }

        // Parse the text into lines
        $lines = array_values(array_filter(array_map('trim', explode("\n", $ocrText))));

        // Extract product section
        $productLines = $this->extractJumboAppProductSection($lines);
        if (empty($productLines)) {
            $productLines = $this->extractJumboReceipt($lines);
        }

        // Parse products from lines
        $products = $this->parseJumboProducts($productLines);

        if (empty($products)) {
            Storage::put('ocr_debug_output.txt', $ocrText);
            Log::warning('Geen producten gevonden in OCR output', ['file' => $filePath]);
        }

        // Update grocery list with found products
        $items = $this->updateGroceryListWithProducts($products);

        return [
            'products' => $items['products'] ?? [],
            'new_products' => $items['new_products'] ?? [],
            'debug_raw_ocr' => $ocrText,
        ];
    }

    /**
     * Perform OCR using available service (OCR.space API)
     *
     * @param string $filePath Path to image file
     * @return array ['text' => string, 'error' => string|null]
     */
    private function performOCR($filePath)
    {
        // Option 1: Try OCR.space API (Free tier available)
        $apiKey = env('OCR_SPACE_API_KEY', 'K87899142388957'); // Free demo key

        try {
            // Read file as base64
            $base64Image = base64_encode(file_get_contents($filePath));

            $response = Http::asForm()->post('https://api.ocr.space/parse/image', [
                'apikey' => $apiKey,
                'base64Image' => 'data:image/png;base64,' . $base64Image,
                'language' => 'dut', // Dutch
                'isOverlayRequired' => 'false',
                'detectOrientation' => 'true',
                'scale' => 'true',
                'OCREngine' => '2', // OCR Engine 2 is better for receipts
            ]);

            if ($response->successful()) {
                $result = $response->json();

                Log::info('OCR.space API response', ['result' => $result]);

                if (isset($result['ParsedResults'][0]['ParsedText'])) {
                    return [
                        'text' => $result['ParsedResults'][0]['ParsedText'],
                        'error' => null,
                    ];
                }

                if (isset($result['ErrorMessage'])) {
                    Log::error('OCR.space API error', ['error' => $result['ErrorMessage']]);
                    return [
                        'text' => '',
                        'error' => 'OCR API fout: ' . json_encode($result['ErrorMessage']),
                        'raw_text' => json_encode($result),
                    ];
                }
            }

            Log::error('OCR.space API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'text' => '',
                'error' => 'OCR API request mislukt: HTTP ' . $response->status(),
                'raw_text' => $response->body(),
            ];

        } catch (\Exception $e) {
            Log::error('OCR API exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'text' => '',
                'error' => 'OCR fout: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Extract product section from Jumbo app receipt
     */
    private function extractJumboAppProductSection(array $lines): array
    {
        $start = false;
        $section = [];

        foreach ($lines as $line) {
            if (!$start && preg_match('/^Producten$/i', $line)) {
                $start = true;
                continue;
            }

            if ($start) {
                if (preg_match('/^Totaal/i', $line)) {
                    break;
                }
                if (strlen(trim($line)) > 0) {
                    $section[] = $line;
                }
            }
        }

        return $section;
    }

    /**
     * Extract products from general Jumbo receipt
     */
    private function extractJumboReceipt(array $lines): array
    {
        $section = [];

        foreach ($lines as $line) {
            // Stop at separator lines
            if (preg_match('/^-{3,}|={3,}/', trim($line))) {
                break;
            }

            if (strlen(trim($line)) > 0) {
                $section[] = $line;
            }
        }

        return $section;
    }

    /**
     * Parse products from text lines
     * Uses a two-pass approach: first collect product names, then match with prices
     */
    private function parseJumboProducts(array $lines): array
    {
        $products = [];

        // First, try to find all product names and prices in the section
        $productNames = [];
        $prices = [];
        $quantities = [];

        foreach ($lines as $line) {
            $clean = trim($line);

            // Skip empty lines and markers
            if (empty($clean) || preg_match('/^[A-Z]$/u', $clean)) {
                continue;
            }

            // Skip headers and footers
            if (preg_match('/^(TEVREDEN|AJ Jumbo|Totaal|Maestro)/i', $clean)) {
                continue;
            }

            // Check if it's a quantity format like "2X1,79"
            if (preg_match('/^(\d+)\s*[xX]\s*(\d{1,3}[.,]\d{2})\s*$/ui', $clean, $m)) {
                $quantities[] = [
                    'quantity' => (int)$m[1],
                    'price' => (float)str_replace(',', '.', $m[2]),
                ];
                continue;
            }

            // Check if it's just a price like "2,15" or "€15,66"
            if (preg_match('/^[€]?\s*(\d{1,3}[.,]\d{2})\s*$/u', $clean, $m)) {
                $prices[] = (float)str_replace(',', '.', $m[1]);
                continue;
            }

            // Check if it's a negative price (discount)
            if (preg_match('/^-\s*[€]?\s*(\d{1,3}[.,]\d{2})\s*$/u', $clean, $m)) {
                // Skip discounts for now
                continue;
            }

            // If it looks like a product name (starts with letter, no price)
            if (preg_match('/^[A-Za-z].+$/u', $clean) && !preg_match('/\d{1,3}[.,]\d{2}/', $clean)) {
                $productNames[] = $clean;
                continue;
            }
        }

        // Now match products with their prices/quantities
        $priceIndex = 0;
        $quantityIndex = 0;

        foreach ($productNames as $name) {
            // Check if we have a matching quantity entry
            if ($quantityIndex < count($quantities)) {
                $qtyInfo = $quantities[$quantityIndex];
                $products[] = [
                    'name' => $name,
                    'quantity' => $qtyInfo['quantity'],
                    'unit_price' => $qtyInfo['price'],
                    'total_price' => $qtyInfo['quantity'] * $qtyInfo['price'],
                ];
                $quantityIndex++;
            } elseif ($priceIndex < count($prices)) {
                // Otherwise use a simple price
                $price = $prices[$priceIndex];
                $products[] = [
                    'name' => $name,
                    'quantity' => 1,
                    'unit_price' => $price,
                    'total_price' => $price,
                ];
                $priceIndex++;
            }
        }

        return $products;
    }

    /**
     * Update grocery list items with extracted products
     */
    private function updateGroceryListWithProducts(array $products): array
    {
        $listIds = GroceryList::all()->pluck('id')->toArray();

        if (empty($listIds)) {
            Log::warning('No grocery lists found');
            return [];
        }

        $groceryListItems = GroceryListItem::whereIn('list_id', $listIds)->get();
        $items = [];

        foreach ($products as $product) {
            $productName = mb_strtolower($product['name']);
            $bestMatch = null;
            $bestDistance = null;

            // Try to find matching item in existing grocery list
            foreach ($groceryListItems as $item) {
                $itemName = mb_strtolower($item->name);

                // Direct substring match
                if (mb_stripos($productName, $itemName) !== false || mb_stripos($itemName, $productName) !== false) {
                    $bestMatch = $item;
                    break;
                }

                // Calculate Levenshtein distance
                $distance = levenshtein($productName, $itemName);
                if ($bestDistance === null || $distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestMatch = $item;
                }
            }

            // Update existing item if match is good enough
            if ($bestMatch && ($bestDistance !== null && $bestDistance <= 3 ||
                    mb_stripos($productName, $bestMatch->name) !== false ||
                    mb_stripos($bestMatch->name, $productName) !== false)) {

                $oldPrice = $bestMatch->unit_price ?? $bestMatch->price ?? 0;
                $newPrice = $product['unit_price'];

                $items['products'][] = [
                    'action' => 'update',
                    'name' => $bestMatch->name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                ];
            } else {
                $newItem = new GroceryListItem();
                $newItem->name = $product['name'];
                $newItem->unit_price = $product['unit_price'];
                $newItem->quantity = $product['quantity'] ?? 1;
                $newItem->checked = true;
                $newItem->list_id = $listIds[0] ?? null;

                $items['new_products'][] = [
                    'action' => 'create',
                    'name' => $newItem->name,
                    'unit_price' => $newItem->unit_price,
                    'quantity' => $newItem->quantity,
                ];
            }
        }

        return $items;
    }
}
