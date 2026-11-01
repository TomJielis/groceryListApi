<?php

namespace Tests\Feature;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Recipes from another recipe app (Receptenmaker) go into one cookbook of a user, with their photo
class ImportRecipesTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Recipe::IMAGE_DISK);

        $photo = imagecreatetruecolor(40, 20);
        ob_start();
        imagejpeg($photo);
        Http::fake([
            'photos.example.com/ok.jpg' => Http::response(ob_get_clean()),
            'photos.example.com/broken.jpg' => Http::response('not a photo'),
        ]);

        $this->file = sys_get_temp_dir() . '/recipes-' . uniqid() . '/recepten.json';
        mkdir(dirname($this->file));
        file_put_contents($this->file, json_encode([
            [
                'id' => '11', 'title' => 'Kip tikka masala',
                'ingredients' => "Voor de kip:\n• 500 gr kipfilet\n- 1,5 el garam masala\n*½ ui\n\nverse koriander\n",
                'method' => "Bereiding:\n1. Snijd de kip.\n⸻\nStap 2\nBak de kip.<br />", 'notes' => 'Lekker met naan', 'servings' => '4', 'prep_minutes' => '',
                'cook_minutes' => '30', 'oven_minutes' => '', 'oven_celsius' => '', 'kcal' => '650', 'protein' => '', 'carbs' => '', 'fat' => '',
                'source_url' => 'https://www.google.com/search?q=https%3A%2F%2Fexample.com%2Ftikka%2F&sca_esv=1',
                'categories' => ['Kip'], 'cookbooks' => ['Iesje'], 'images' => ['https://photos.example.com/ok.jpg'],
            ],
            [
                'id' => '12', 'title' => '', 'ingredients' => '', 'method' => '', 'notes' => '', 'servings' => '0', 'prep_minutes' => '',
                'cook_minutes' => '', 'oven_minutes' => '', 'oven_celsius' => '999', 'kcal' => '', 'protein' => '', 'carbs' => '', 'fat' => '',
                'source_url' => '', 'categories' => ['Nagerecht'], 'cookbooks' => [], 'images' => ['https://photos.example.com/broken.jpg'],
            ],
        ]));
    }

    public function test_recipes_go_into_the_given_cookbook_with_their_photo(): void
    {
        $user = User::factory()->create();
        $cookbook = Cookbook::create(['name' => 'Mijn recepten', 'created_by' => $user->id]);

        $this->assertSame(0, Artisan::call('recipes:import', ['file' => $this->file, '--user' => $user->email, '--cookbook' => $cookbook->id]));

        $this->assertSame(2, Recipe::where('cookbook_id', $cookbook->id)->count());
        // The other app's cookbooks aren't made
        $this->assertSame(1, Cookbook::count());

        $tikka = Recipe::where('title', 'Kip tikka masala')->sole();
        $this->assertSame('main', $tikka->category);
        $this->assertSame(4, $tikka->servings);
        $this->assertSame(30, $tikka->cook_minutes);
        $this->assertSame(650, $tikka->kcal);
        $this->assertSame('Lekker met naan', $tikka->description);
        $this->assertSame('https://example.com/tikka/', $tikka->source_url);
        $this->assertSame($user->id, $tikka->created_by);
        $this->assertSame(
            [['Kipfilet', 500.0, 'g'], ['Garam masala', 1.5, 'el'], ['Ui', 0.5, ''], ['Verse koriander', null, '']],
            $tikka->ingredients->map(fn ($i) => [$i->name, $i->amount === null ? null : (float) $i->amount, $i->unit])->all()
        );
        $this->assertSame(['Snijd de kip.', 'Bak de kip.'], $tikka->steps->pluck('body')->all());
        Storage::disk(Recipe::IMAGE_DISK)->assertExists([$tikka->image_path, Recipe::thumbPath($tikka->image_path)]);

        // An incomplete one still comes in, with what's usable
        $untitled = Recipe::where('title', 'Naamloos recept')->sole();
        $this->assertSame('dessert', $untitled->category);
        $this->assertSame(4, $untitled->servings);
        $this->assertNull($untitled->oven_celsius);
        $this->assertNull($untitled->image_path);
    }

    public function test_running_it_again_adds_nothing_twice(): void
    {
        $user = User::factory()->create();
        $cookbook = Cookbook::create(['name' => 'Mijn recepten', 'created_by' => $user->id]);
        $options = ['file' => $this->file, '--user' => $user->email, '--cookbook' => $cookbook->id];

        Artisan::call('recipes:import', $options);
        Artisan::call('recipes:import', $options);

        $this->assertSame(2, Recipe::count());
    }

    public function test_only_into_a_cookbook_the_user_can_see_and_a_dry_run_changes_nothing(): void
    {
        $user = User::factory()->create();
        $someoneElses = Cookbook::create(['name' => 'Van iemand anders', 'created_by' => User::factory()->create()->id]);

        $this->assertSame(1, Artisan::call('recipes:import', ['file' => $this->file, '--user' => $user->email, '--cookbook' => $someoneElses->id]));

        $own = Cookbook::create(['name' => 'Mijn recepten', 'created_by' => $user->id]);
        Artisan::call('recipes:import', ['file' => $this->file, '--user' => $user->email, '--cookbook' => $own->id, '--dry-run' => true]);
        $this->assertSame(0, Recipe::count());
    }
}
