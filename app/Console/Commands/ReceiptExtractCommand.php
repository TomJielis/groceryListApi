<?php

namespace App\Console\Commands;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use Illuminate\Console\Command;
use App\Services\ReceiptOcrService;

//tesseract  /Users/tomjielisvanwijgerden/projects/groceryListApi/storage/app/private/receipts/receipt_68f4d1ce2debf6.12251870.png stdout -l nld+eng --psm 6


class ReceiptExtractCommand extends Command
{
    protected $signature = 'receipt:extract {file? : Path to the receipt image or PDF (optioneel)} {--raw : Sla preprocessing over en gebruik direct het origineel}';
    protected $description = 'Voer OCR uit op een bonbestand en toon de herkende producten en prijzen';

    public function handle()
    {
        auth()->loginUsingId(3);
        $dir = storage_path('app/private/receipts');
        if (!is_dir($dir)) {
            $this->error("Directory niet gevonden: $dir");
            return 1;
        }
        $files = glob($dir . '/*');
        if (!$files || count($files) === 0) {
            $this->info('Geen bestanden gevonden in ' . $dir);
            return 0;
        }
        $ocrService = new ReceiptOcrService();
        $useRaw = $this->option('raw');
        foreach ($files as $file) {
            $this->info("\nBestand: $file");
            if (!is_file($file) || !is_readable($file)) {
                $this->error("  Kan bestand niet lezen: $file");
                continue;
            }
            $result = $ocrService->extractProductsAndPricesFromFile($file, $useRaw);

            if (isset($result['debug_raw_ocr'])) {
                $this->line("  [DEBUG] Ruwe OCR-output:\n" . $result['debug_raw_ocr']);
            }
            if (isset($result['debug_tesseract_cmd'])) {
                $this->line("  [DEBUG] Tesseract commando: " . $result['debug_tesseract_cmd']);
            }
            if (isset($result['products']['error'])) {
                $this->error('  ' . $result['products']['error']);
                continue;
            }
            $this->line(json_encode($result['products'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        return 0;
    }
}
