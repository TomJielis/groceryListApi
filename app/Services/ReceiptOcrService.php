<?php

namespace App\Services;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use Illuminate\Support\Facades\Storage;

class ReceiptOcrService
{
    /**
     * OCR uitvoeren + producten herkennen (specifiek voor Jumbo)
     */
    public function extractProductsAndPricesFromFile($filePath, $raw = false)
    {
        $text = $this->runTesseract($filePath, $raw, $debugCmd);
        $debug = [
            'debug_raw_ocr' => $text,
            'debug_tesseract_cmd' => $debugCmd ?? null,
        ];
        // Controleer of OCR-tekst geldig is
        if (!is_string($text)) {
            return array_merge([
                'products' => ['error' => 'OCR-proces gaf geen tekst terug (geen string)'],
                'raw_product_section' => [],
            ], $debug);
        }
        if (empty(trim($text))) {
            return array_merge([
                'products' => ['error' => 'Geen tekst herkend uit afbeelding (lege OCR-output)'],
                'raw_product_section' => [],
            ], $debug);
        }
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
        $productLines = $this->extractJumboProductSection($lines);
        $products = $this->parseJumboProducts($productLines);
        if (empty($products)) {
            Storage::put('ocr_debug_output.txt', $text);
        }

        $updatedItems = $this->updateGroceryListWithProducts($products);

        return array_merge([
            'products' => $products,
            'updated_items' => $updatedItems,
        ]);
    }

    private function extractJumboProductSection(array $lines): array
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
                if (strlen(trim($line)) > 1) {
                    $section[] = $line;
                }
            }
        }
        return $section;
    }

    private function parseJumboProducts(array $lines): array
    {
        $products = [];
        $i = 0;
        $count = count($lines);
        while ($i < $count) {
            $line = trim($lines[$i]);
            $clean = preg_replace('/[€]/', '', $line);
            $clean = preg_replace('/\s{2,}/', ' ', $clean);
            $clean = trim($clean);

            // Corrigeer veelvoorkomende OCR-fouten in productnamen
            $cleanName = preg_replace('/11\./', '1l.', $clean);
            $cleanName = preg_replace('/11(\s|$)/', '1l$1', $cleanName);
            $cleanName = preg_replace('/1 l/', '1l', $cleanName);

            // Als de regel begint met hoeveelheid of getal, nooit als los product toevoegen
            if (preg_match('/^(\d+\s*[xX]|\d{1,3}(?:[.,]\d{2}))/u', $clean)) {
                $i++;
                continue;
            }

            // Patroon: "naamregel" gevolgd door "2 X 5,20 10,40"
            if (
                preg_match('/^[A-Za-z].+$/u', $cleanName) &&
                ($i + 1 < $count) &&
                preg_match('/^(\d+)\s*[xX]\s*(\d{1,3}(?:[.,]\d{2}))\s+((?:[A-Za-z ]*)?)(\d{1,3}(?:[.,]\d{2}))$/u', trim($lines[$i+1]), $m2)
            ) {
                $products[] = [
                    'name' => $cleanName,
                    'quantity' => (int) $m2[1],
                    'unit_price' => (float) str_replace(',', '.', $m2[2]),
                    'total_price' => (float) str_replace(',', '.', $m2[4]),
                ];
                $i += 2;
                continue;
            }
            // Patroon: "naam + prijs" op één regel
            if (preg_match('/^(.+?)\s+(\d{1,3}(?:[.,]\d{2}))$/u', $cleanName, $m)) {
                $products[] = [
                    'name' => trim($m[1]),
                    'quantity' => 1,
                    'unit_price' => (float) str_replace(',', '.', $m[2]),
                    'total_price' => (float) str_replace(',', '.', $m[2]),
                ];
                $i++;
                continue;
            }
            // Patroon: "naam + hoeveelheid + prijs + totaal" op één regel
            if (preg_match('/^(.+?)\s+(\d+)\s*[xX]\s*(\d{1,3}(?:[.,]\d{2}))\s+(\d{1,3}(?:[.,]\d{2}))$/u', $cleanName, $m)) {
                $products[] = [
                    'name' => trim($m[1]),
                    'quantity' => (int) $m[2],
                    'unit_price' => (float) str_replace(',', '.', $m[3]),
                    'total_price' => (float) str_replace(',', '.', $m[4]),
                ];
                $i++;
                continue;
            }
            // Anders: skip (geen product)
            $i++;
        }
        return $products;
    }

    public function runTesseract($filePath, $raw = false, &$debugCmd = null)
    {
        if (!file_exists($filePath)) {
            \Log::error('runTesseract: bestand niet gevonden', ['path' => $filePath]);
            return '';
        }
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $lang = 'nld+eng';
        $tmpTxt = @tempnam(sys_get_temp_dir(), 'ocr_');
        if ($ext === 'pdf') {
            $base = @tempnam(sys_get_temp_dir(), 'pdf_');
            unlink($base);
            shell_exec("pdftoppm -png -f 1 -singlefile " . escapeshellarg($filePath) . " " . escapeshellarg($base));
            $filePath = $base . '.png';
        }
        $ocrInput = $filePath;
        if (!$raw) {
            $tmpProcessed = '/tmp/ocr_debug.png';
            if (file_exists($tmpProcessed)) {
                @unlink($tmpProcessed);
                \Log::info('Removed stale /tmp/ocr_debug.png before magick');
            }
            $magickAvailable = (bool) shell_exec('command -v magick');
            if ($magickAvailable) {
                $magickCmd = "magick " . escapeshellarg($filePath) . " -colorspace Gray -contrast-stretch 5% " . escapeshellarg($tmpProcessed);
                $magickOutput = shell_exec($magickCmd . ' 2>&1');
                $fileExists = file_exists($tmpProcessed);
                \Log::info('magick output', [
                    'cmd' => $magickCmd,
                    'output' => $magickOutput,
                    'tmpProcessed' => $tmpProcessed,
                    'tmpProcessed_exists' => $fileExists
                ]);
                if ($fileExists) {
                    $ocrInput = $tmpProcessed;
                } else {
                    \Log::warning('Fallback to original file for OCR because magick did not produce output file', [
                        'cmd' => $magickCmd,
                        'output' => $magickOutput,
                        'tmpProcessed_exists' => $fileExists
                    ]);
                }
            } else {
                \Log::error('ImageMagick magick not found, using original file');
            }
            if ($ocrInput === $tmpProcessed && !file_exists($tmpProcessed)) {
                \Log::critical('FINAL fallback: /tmp/ocr_debug.png missing before Tesseract, forcibly using original file', [
                    'ocrInput' => $ocrInput,
                    'tmpProcessed_exists' => false
                ]);
                $ocrInput = $filePath;
            }
        }
        \Log::info('Tesseract OCR input file (final)', ['ocrInput' => $ocrInput, 'ocrInput_exists' => file_exists($ocrInput)]);
        $languages = ['nld+eng', 'eng', 'nld'];
        $psms = [6, 4];
        $text = '';
        foreach ($languages as $lang) {
            foreach ($psms as $psm) {
                $cmd = "tesseract " . escapeshellarg($ocrInput) . " " . escapeshellarg($tmpTxt) . " -l " . escapeshellarg($lang) . " --psm $psm 2>&1";
                $debugCmd = $cmd;
                $output = shell_exec($cmd);
                \Log::info('tesseract output', ['cmd' => $cmd, 'output' => $output]);
                $text = @file_get_contents($tmpTxt . '.txt');
                if (!empty(trim($text))) break 2;
            }
        }
        @unlink($tmpTxt);
        @unlink($tmpTxt . '.txt');
        if (empty(trim($text))) {
            \Log::error('runTesseract: OCR gaf geen tekst terug', ['file' => $filePath]);
            return '';
        }

        return $text ?: '';
    }

    private function updateGroceryListWithProducts(array $products): array
    {

        $listIds = GroceryList::all()->pluck('id')->toArray();

        $groceryListItems = GroceryListItem::where('list_id', $listIds)
            ->get();

        $updatedItems = [];
        foreach ($products as $product) {
            $productName = mb_strtolower($product['name']);
            $bestMatch = null;
            $bestDistance = null;
            foreach ($groceryListItems as $item) {
                $itemName = mb_strtolower($item->name);
                if (mb_stripos($productName, $itemName) !== false || mb_stripos($itemName, $productName) !== false) {
                    $bestMatch = $item;
                    break;
                }
                $distance = levenshtein($productName, $itemName);
                if ($bestDistance === null || $distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestMatch = $item;
                }
            }
            if ($bestMatch && ($bestDistance !== null && $bestDistance <= 3 || mb_stripos($productName, $bestMatch->name) !== false || mb_stripos($bestMatch->name, $productName) !== false)) {
                $oldPrice = $bestMatch->price;
                $newPrice = $product['unit_price'];
                if ($oldPrice != $newPrice) {
                    $updatedItems[] = [
                        'action' => 'update',
                        'name' => $bestMatch->name,
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                    ];
                    $bestMatch->unit_price = $newPrice;
                    $bestMatch->save();
                }
            } else {
                $newItem = new GroceryListItem();
                $newItem->name = $product['name'];
                $newItem->unit_price = $product['unit_price'];
                $newItem->quantity = $product['quantity'] ?? 1;
                $newItem->checked = true;
                $newItem->list_id = $listIds[0] ?? null;
                $newItem->save();
                $updatedItems[] = [
                    'action' => 'create',
                    'name' => $newItem->name,
                    'unit_price' => $newItem->price,
                    'quantity' => $newItem->quantity,
                ];
            }
        }

        return $updatedItems;
    }
}
