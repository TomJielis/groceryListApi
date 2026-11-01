<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Recipe extends Model
{
    public const CATEGORIES = ['breakfast', 'lunch', 'main', 'side', 'dessert'];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    // Photos are on the private disk: the app gets them through RecipeController::image, for members only
    public const IMAGE_DISK = 'local';

    protected $fillable = [
        'cookbook_id',
        'created_by',
        'title',
        'description',
        'category',
        'servings',
        'prep_minutes',
        'cook_minutes',
        'oven_minutes',
        'oven_celsius',
        'difficulty',
        'kcal',
        'protein',
        'carbs',
        'fat',
        'source_url',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'servings' => 'integer',
            'prep_minutes' => 'integer',
            'cook_minutes' => 'integer',
            'oven_minutes' => 'integer',
            'oven_celsius' => 'integer',
            'kcal' => 'integer',
            'protein' => 'integer',
            'carbs' => 'integer',
            'fat' => 'integer',
        ];
    }

    public function cookbook(): BelongsTo
    {
        return $this->belongsTo(Cookbook::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('position');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)->orderBy('position');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'recipe_favorites')->withTimestamps();
    }

    /**
     * Recipes in the cookbooks the user may see (Cookbook::scopeVisibleTo)
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('cookbook_id', Cookbook::visibleTo($user)->select('id'));
    }

    // The small copy for the overview, saved next to the photo by RecipeImageStore
    public static function thumbPath(string $imagePath): string
    {
        return preg_replace('/\.jpg$/', '', $imagePath) . '-thumb.jpg';
    }

    /**
     * Ingredients and steps are replaced as a whole: the app always sends the full recipe
     *
     * @param list<array{name: string, amount?: float|int|null, unit?: string|null}> $ingredients
     * @param list<string> $steps
     */
    public function replaceParts(array $ingredients, array $steps): void
    {
        $this->ingredients()->delete();
        $this->steps()->delete();

        foreach (array_values($ingredients) as $position => $ingredient) {
            $this->ingredients()->create([
                'position' => $position,
                'name' => $ingredient['name'],
                'amount' => $ingredient['amount'] ?? null,
                'unit' => $ingredient['unit'] ?? '',
            ]);
        }
        foreach (array_values($steps) as $position => $body) {
            $this->steps()->create(['position' => $position, 'body' => $body]);
        }
    }

    // The photo and its small copy
    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk(self::IMAGE_DISK)->delete([$this->image_path, self::thumbPath($this->image_path)]);
        }
    }
}
