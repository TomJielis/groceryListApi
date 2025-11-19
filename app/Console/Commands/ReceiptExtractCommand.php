<?php

namespace App\Console\Commands;

use App\Models\GroceryList;
use App\Models\GroceryListItem;
use Illuminate\Console\Command;
use App\Services\ReceiptOcrService;

class ReceiptExtractCommand extends Command
{
    protected $signature = 'receipt:extract {file? : Path to the receipt image or PDF (optioneel)} {--raw : Sla preprocessing over en gebruik direct het origineel}';
    protected $description = 'Voer OCR uit op een bonbestand en toon de herkende producten en prijzen';

    public function handle()
    {
        auth()->loginUsingId(3);

        // Get file path from argument or use all files in receipts directory
        $inputFile = $this->argument('file');

        if ($inputFile) {
            if (!file_exists($inputFile)) {
                $this->error("Bestand niet gevonden: $inputFile");
                return 1;
            }
            $files = [$inputFile];
        } else {
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
        }

        $ocrService = new ReceiptOcrService();
        $useRaw = $this->option('raw');

        foreach ($files as $file) {
            $this->info("\n" . str_repeat('=', 60));
            $this->info("📄 Bestand: " . basename($file));
            $this->info(str_repeat('=', 60));

            if (!is_file($file) || !is_readable($file)) {
                $this->error("  ❌ Kan bestand niet lezen: $file");
                continue;
            }

            // Extract products
            $result = $ocrService->extractProductsAndPricesFromFile($file, $useRaw);

            // Show debug info
            if (isset($result['debug_raw_ocr'])) {
                $this->newLine();
                $this->line("🔍 Ruwe OCR-output:");
                $this->line(str_repeat('-', 60));
                $this->line(substr($result['debug_raw_ocr'], 0, 500) . (strlen($result['debug_raw_ocr']) > 500 ? '...' : ''));
                $this->line(str_repeat('-', 60));
            }

            // Show errors
            if (isset($result['products']['error'])) {
                $this->error("\n❌ " . $result['products']['error']);
                continue;
            }

            // Show products
            if (empty($result['products'])) {
                $this->warn("\n⚠️  Geen producten gevonden in deze bon");
            } else {
                $this->newLine();
                $this->info("✅ Gevonden producten (" . count($result['products']) . "):");
                $this->newLine();

                $this->table(
                    ['Product', 'Aantal', 'Prijs/stuk', 'Totaal'],
                    array_map(function($p) {
                        return [
                            $p['name'],
                            $p['quantity'],
                            '€ ' . number_format($p['unit_price'], 2, ',', '.'),
                            '€ ' . number_format($p['total_price'], 2, ',', '.'),
                        ];
                    }, $result['products'])
                );
            }

            // Show updated items
            if (!empty($result['updated_items'])) {
                $this->newLine();
                $this->info("🔄 Grocery list updates (" . count($result['updated_items']) . "):");

                foreach ($result['updated_items'] as $update) {
                    if ($update['action'] === 'update') {
                        $this->line("  📝 Updated: {$update['name']} - €{$update['old_price']} → €{$update['new_price']}");
                    } else {
                        $this->line("  ➕ Created: {$update['name']} - €{$update['unit_price']} ({$update['quantity']}x)");
                    }
                }
            }
        }

        $this->newLine();
        $this->info("✨ Klaar!");
        return 0;
    }
}
