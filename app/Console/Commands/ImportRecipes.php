<?php

namespace App\Console\Commands;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use App\Services\RecipeImageStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Imports recipes saved from another recipe app (Receptenmaker) for one user, from a JSON file with one object per
 * recipe: id, title, ingredients (one per line), method (steps, one per line), notes, source_url, servings, the times,
 * oven_celsius, kcal/protein/carbs/fat, categories and images (photo addresses). Runs on the server, so no password
 * or login token is needed. All recipes go into one cookbook the user may see (--cookbook, its id), whatever cookbook
 * they were in in the other app.
 *
 * The file can be just a name: "elvin" is storage/app/imports/receptenmaker/elvin.json, where the exports are kept
 * with the code. A path works too.
 *
 * Running it again only adds what wasn't imported yet: the imported ids are kept next to the file. --refresh-photos
 * only fetches the photos again (in the larger size) for the recipes imported before, without adding or removing any.
 *
 * Run it as the web server's user (sudo -u www-data php artisan ...): the photos it saves must be readable for the API.
 */
class ImportRecipes extends Command
{
    protected $signature = 'recipes:import
        {file : JSON file with the recipes, or the name of one in storage/app/imports/receptenmaker}
        {--user= : Email address of the user they are for}
        {--cookbook= : Id of the cookbook they all go into}
        {--dry-run : Only show what would be imported}
        {--refresh-photos : Only fetch the photos again for the recipes imported before}';

    protected $description = 'Import recipes from another recipe app (JSON) into a user\'s cookbooks';

    // The other app's categories, in ours (App\Enums\RecipeCategory); one about something else ("Kip", "Vegetarisch")
    // gives none
    private const CATEGORIES = [
        'hoofdgerecht' => 'main',
        'ovenschotel' => 'main',
        'pasta' => 'main',
        'ontbijt' => 'breakfast',
        'brunch' => 'breakfast',
        'lunch' => 'lunch',
        'soep' => 'lunch',
        'soepen' => 'lunch',
        'bijgerecht' => 'side',
        'salade' => 'side',
        'voorgerecht' => 'side',
        'amuse' => 'side',
        'tussengerecht' => 'side',
        'snacks & drinks' => 'snack',
        'snacks' => 'snack',
        'snack' => 'snack',
        'nagerecht' => 'dessert',
        'dessert' => 'dessert',
        'ijs' => 'dessert',
        'cheesecake' => 'dessert',
        'patisserie' => 'dessert',
    ];

    // Written units and the way the app writes them (like utils/recipe-import.ts in the app)
    private const UNITS = [
        'g' => 'g', 'gr' => 'g', 'gr.' => 'g', 'gram' => 'g', 'grams' => 'g',
        'kg' => 'kg', 'kilo' => 'kg', 'kilogram' => 'kg', 'mg' => 'mg',
        'oz' => 'oz', 'ounce' => 'oz', 'ounces' => 'oz', 'lb' => 'lb', 'lbs' => 'lb', 'pound' => 'lb', 'pounds' => 'lb',
        'ml' => 'ml', 'milliliter' => 'ml', 'cl' => 'cl', 'dl' => 'dl', 'l' => 'l', 'liter' => 'l',
        'el' => 'el', 'eetlepel' => 'el', 'eetlepels' => 'el', 'tl' => 'tl', 'theelepel' => 'tl', 'theelepels' => 'tl',
        'stuk' => 'stuk', 'stuks' => 'stuks', 'st' => 'stuk', 'st.' => 'stuk',
        'blik' => 'blik', 'blikje' => 'blikje', 'blikjes' => 'blikjes',
        'pot' => 'pot', 'potje' => 'potje', 'potjes' => 'potjes',
        'zak' => 'zak', 'zakje' => 'zakje', 'zakjes' => 'zakjes',
        'pak' => 'pak', 'pakje' => 'pak', 'pakken' => 'pak', 'fles' => 'fles', 'flesje' => 'fles',
        'bosje' => 'bosje', 'bosjes' => 'bosjes', 'teen' => 'teen', 'teentje' => 'teentje', 'teentjes' => 'teentjes',
        'takje' => 'takje', 'takjes' => 'takjes', 'plak' => 'plak', 'plakje' => 'plakje', 'plakjes' => 'plakjes',
        'plakken' => 'plakken', 'snuf' => 'snuf', 'snufje' => 'snufje', 'krop' => 'krop', 'bol' => 'bol',
        'bollen' => 'bollen', 'kopje' => 'kopje', 'kopjes' => 'kopjes', 'scheut' => 'scheut', 'scheutje' => 'scheutje',
    ];

    private const FRACTIONS = ['½' => 0.5, '¼' => 0.25, '¾' => 0.75, '⅓' => 1 / 3, '⅔' => 2 / 3, '⅛' => 0.125];

    public function __construct(private RecipeImageStore $images)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $user = User::where('email', $this->option('user'))->first();
        if (!$user) {
            $this->error('No user with the email address ' . $this->option('user') . '.');
            return self::FAILURE;
        }

        $cookbook = $this->cookbook($user, (string) $this->option('cookbook'));
        if (!$cookbook) {
            return self::FAILURE;
        }

        $file = $this->file($this->argument('file'));
        $recipes = is_file($file) ? json_decode(file_get_contents($file), true) : null;
        if (!is_array($recipes)) {
            $this->error("{$file} is not a JSON file with recipes.");
            return self::FAILURE;
        }

        // Ids imported before (for this user), so running it again doesn't add them twice
        $logFile = dirname($file) . '/imported-user-' . $user->id . '.json';
        $imported = is_file($logFile) ? json_decode(file_get_contents($logFile), true) : [];
        if ($this->option('refresh-photos')) {
            return $this->refreshPhotos($recipes, $imported, $user, $cookbook);
        }

        $todo = array_values(array_filter($recipes, fn (array $recipe) => !in_array((string) $recipe['id'], $imported, true)));

        $this->info(count($todo) . ' of ' . count($recipes) . " recipes to import for {$user->email} into \"{$cookbook->name}\".");
        if ($this->option('dry-run')) {
            foreach ($todo as $recipe) {
                $this->line('- ' . $this->title($recipe));
            }
            return self::SUCCESS;
        }

        $withoutPhoto = 0;
        foreach ($todo as $recipe) {
            $imagePath = $this->photo($recipe);
            if (!empty($recipe['images']) && !$imagePath) {
                $withoutPhoto++;
            }

            DB::transaction(function () use ($recipe, $user, $cookbook, $imagePath) {
                $model = Recipe::create([
                    'cookbook_id' => $cookbook->id,
                    'created_by' => $user->id,
                    'title' => $this->title($recipe),
                    'description' => $this->text($recipe['notes'] ?? '', 5000),
                    'categories' => $this->categories($recipe['categories'] ?? []),
                    'servings' => $this->number($recipe['servings'] ?? null, 1, 100) ?? 4,
                    'prep_minutes' => $this->number($recipe['prep_minutes'] ?? null, 0, 1440) ?? 0,
                    'cook_minutes' => $this->number($recipe['cook_minutes'] ?? null, 0, 1440),
                    'oven_minutes' => $this->number($recipe['oven_minutes'] ?? null, 0, 1440),
                    'oven_celsius' => $this->number($recipe['oven_celsius'] ?? null, 30, 300),
                    'difficulty' => 'easy',
                    'kcal' => $this->number($recipe['kcal'] ?? null, 0, 10000),
                    'protein' => $this->number($recipe['protein'] ?? null, 0, 1000),
                    'carbs' => $this->number($recipe['carbs'] ?? null, 0, 1000),
                    'fat' => $this->number($recipe['fat'] ?? null, 0, 1000),
                    'source_url' => $this->sourceUrl($recipe['source_url'] ?? ''),
                    'image_path' => $imagePath,
                ]);
                $model->replaceParts(
                    array_map(fn (string $line) => $this->ingredient($line), $this->lines($recipe['ingredients'] ?? '', 100, false)),
                    array_map(fn (string $line) => mb_substr($line, 0, 5000), $this->lines($recipe['method'] ?? '', 100, true)),
                );
            });

            $imported[] = (string) $recipe['id'];
            file_put_contents($logFile, json_encode($imported));
            $this->line('✓ ' . $this->title($recipe));
        }

        $this->info('Imported ' . count($todo) . ' recipes' . ($withoutPhoto ? ", {$withoutPhoto} without their photo (it couldn't be downloaded)" : '') . '.');
        return self::SUCCESS;
    }

    // The cookbook by its id, only one the user can see; by id only, so a name can't pick the wrong one
    private function cookbook(User $user, string $id): ?Cookbook
    {
        $cookbook = ctype_digit($id) ? Cookbook::visibleTo($user)->find($id) : null;
        if (!$cookbook) {
            $this->error("No cookbook with id {$id} that {$user->email} can see.");
            $this->line('Their cookbooks: ' . Cookbook::visibleTo($user)->orderBy('id')->get()->map(fn (Cookbook $c) => "{$c->id} \"{$c->name}\"")->implode(', '));
        }

        return $cookbook;
    }

    // A path as given, or a name (with or without .json) of an export kept in storage/app/imports/receptenmaker
    private function file(string $file): string
    {
        if (is_file($file)) {
            return $file;
        }
        $kept = storage_path('app/imports/receptenmaker/' . basename($file, '.json') . '.json');

        return is_file($kept) ? $kept : $file;
    }

    private function title(array $recipe): string
    {
        return $this->text($recipe['title'] ?? '', 255) ?: 'Naamloos recept';
    }

    private function categories(array $categories): array
    {
        $ours = [];
        foreach ($categories as $category) {
            $ours[] = self::CATEGORIES[mb_strtolower(trim($category))] ?? null;
        }

        return array_values(array_unique(array_filter($ours)));
    }

    private function text(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * The usable lines of a text field, without what the other app's texts are dressed up with: bullets ("• 2 eieren",
     * "- Mango"), step numbers ("1. Snijd", "Stap 2:"), HTML line breaks, separators ("⸻", "---") and headings
     * ("Voor de saus:", "Bereiding"). Our recipes have no sections, so a heading would become an ingredient or step.
     */
    private function lines(?string $value, int $max, bool $steps): array
    {
        $lines = [];
        foreach (preg_split('/\R|<br\s*\/?>/iu', (string) $value) as $line) {
            $line = trim(strip_tags($line));
            $line = trim(preg_replace('/^[\-•*–—▢◦·▪●]+\s*/u', '', $line));
            if ($steps) {
                $line = trim(preg_replace('/^(stap\s*)?\d+\s*[.):\-]?\s+|^stap\s*\d+\s*$/iu', '', $line));
            }
            $isHeading = preg_match('/^[\-⸻_=~.\s]*$/u', $line)
                || (str_ends_with($line, ':') && mb_strlen($line) <= 60)
                || preg_match('/^(ingrediënten|ingredienten|benodigdheden|bereiding|bereidingswijze|bereidingsstappen|werkwijze)$/iu', $line);
            if ($line !== '' && !$isHeading) {
                $lines[] = $line;
            }
        }

        return array_slice($lines, 0, $max);
    }

    // A whole number within what RecipeRequest allows, or null when empty or out of range
    private function number(mixed $value, int $min, int $max): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }
        $number = (int) round((float) $value);

        return $number >= $min && $number <= $max ? $number : null;
    }

    // The page itself; a link saved as a Google search for it gives the page it searched for
    private function sourceUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if (str_contains($url, 'google.') && preg_match('/[?&]q=([^&]+)/', $url, $match)) {
            $url = urldecode($match[1]);
        }

        return preg_match('#^https?://#i', $url) && mb_strlen($url) <= 2048 ? $url : null;
    }

    /**
     * Splits an ingredient line ("400 gr spaghetti", "1,5 el olijfolie", "½ ui", "verse peterselie") into amount,
     * unit and name, like parseIngredient in the app. A line without an amount is "to taste".
     */
    private function ingredient(string $line): array
    {
        $fractions = implode('', array_keys(self::FRACTIONS));
        $amount = null;
        $rest = $line;

        if (preg_match('/^(\d+(?:[.,]\d+)?)?\s*([' . $fractions . '])?(?:\s*\/\s*(\d+))?(?:\s*[-–]\s*\d+(?:[.,]\d+)?)?\s*/u', $line, $match)
            && trim($match[0]) !== '') {
            $whole = ($match[1] ?? '') !== '' ? (float) str_replace(',', '.', $match[1]) : null;
            $value = isset($match[3]) && $match[3] !== '' && $whole !== null && (int) $match[3] > 0
                ? $whole / (int) $match[3]
                : ($whole ?? 0) + (($match[2] ?? '') !== '' ? self::FRACTIONS[$match[2]] : 0);
            if ($value > 0) {
                $amount = min(100000, round($value, 2));
                $rest = trim(mb_substr($line, mb_strlen($match[0])));
            }
        }

        $unit = '';
        // The unit ends at a space, a slash or a bracket: "70 gr/1 blikje" is 70 gr
        if ($amount !== null && preg_match('#^([^\s/(]+)\s*(.*)$#u', $rest, $word) && isset(self::UNITS[mb_strtolower($word[1])])) {
            $unit = self::UNITS[mb_strtolower($word[1])];
            $rest = trim($word[2]);
        }
        if ($amount !== null) {
            // The same amount said another way: "70 gr/1 blikje tomatenpuree" is 70 gr tomatenpuree. A bracketed one
            // goes after the name: "4 eetlepels (30 g) bloem" is 4 el bloem (30 g). Like parseIngredient in the app.
            if (preg_match('#^/\s*\d+(?:[.,]\d+)?\s*([^\s,]+)\s+(.+)$#u', $rest, $alternative) && isset(self::UNITS[mb_strtolower($alternative[1])])) {
                $rest = trim($alternative[2]);
            }
            if (preg_match('/^\(([^)]*)\)\s*(.+)$/u', $rest, $bracketed)) {
                $rest = trim($bracketed[2]) . ' (' . trim($bracketed[1]) . ')';
            }
        }

        // A name that would be empty ("2 stuks") keeps the whole line, so nothing gets lost
        if ($rest === '') {
            return ['name' => mb_substr($line, 0, 255), 'amount' => null, 'unit' => ''];
        }

        return ['name' => mb_substr(mb_strtoupper(mb_substr($rest, 0, 1)) . mb_substr($rest, 1), 0, 255), 'amount' => $amount, 'unit' => $unit];
    }

    /**
     * The photos of recipes imported before, fetched again: each recipe in the cookbook is matched to the one in the
     * file it was made from by its title (recipes with the same title in the order they were imported)
     */
    private function refreshPhotos(array $recipes, array $imported, User $user, Cookbook $cookbook): int
    {
        $byTitle = Recipe::where('cookbook_id', $cookbook->id)->where('created_by', $user->id)->orderBy('id')->get()->groupBy('title');
        $refreshed = 0;
        $failed = 0;

        foreach ($recipes as $recipe) {
            if (empty($recipe['images']) || !in_array((string) $recipe['id'], $imported, true)) {
                continue;
            }
            $model = $byTitle->get($this->title($recipe))?->shift();
            if (!$model) {
                continue;
            }
            $imagePath = $this->photo($recipe);
            if (!$imagePath) {
                $failed++;
                continue;
            }
            $model->deleteImage();
            $model->update(['image_path' => $imagePath]);
            $refreshed++;
            $this->line('✓ ' . $model->title);
        }

        $this->info("Fetched {$refreshed} photos again" . ($failed ? ", {$failed} couldn't be downloaded (their old photo stays)" : '') . '.');
        return self::SUCCESS;
    }

    /**
     * The first photo, saved like one taken in the app (smaller JPEG plus the small copy); null when it can't be had.
     * The other app keeps each photo in several sizes; the larger one (1024x768) is tried first.
     */
    private function photo(array $recipe): ?string
    {
        $url = $recipe['images'][0] ?? null;
        if (!$url || !preg_match('#^https://#i', $url)) {
            return null;
        }

        foreach (array_unique([preg_replace('#/\d+x\d+/#', '/1024x768/', $url), $url]) as $candidate) {
            try {
                $response = Http::timeout(20)->get($candidate);
            } catch (\Throwable) {
                continue;
            }
            $contents = $response->successful() ? $response->body() : '';
            if ($contents !== '' && (!function_exists('imagecreatefromstring') || @imagecreatefromstring($contents) !== false)) {
                return $this->images->store($contents);
            }
        }

        return null;
    }
}
