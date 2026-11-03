<?php

namespace App\Models;

use App\Enums\RecipeCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Recipe extends Model
{
    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    // Photos are on the private disk: the app gets them through RecipeController::image, for members only
    public const IMAGE_DISK = 'local';

    protected $fillable = [
        'cookbook_id',
        'created_by',
        // The recipe this copy was saved from (RecipeLinkController::save)
        'copied_from_recipe_id',
        'title',
        'description',
        // Not a column: kept in recipe_categories (setCategoriesAttribute)
        'categories',
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

    // The kinds of meal set on it, saved with it (booted)
    private ?array $pendingCategories = null;

    protected static function booted(): void
    {
        static::saved(function (Recipe $recipe) {
            if ($recipe->pendingCategories === null) {
                return;
            }
            $wanted = $recipe->pendingCategories;
            $recipe->pendingCategories = null;
            $recipe->categoryLinks()->whereNotIn('category', $wanted)->delete();
            $have = $recipe->categoryLinks()->pluck('category')->map(fn (RecipeCategory $category) => $category->value)->all();
            foreach (array_diff($wanted, $have) as $category) {
                $recipe->categoryLinks()->create(['category' => $category]);
            }
            $recipe->unsetRelation('categoryLinks');
        });
    }

    public function categoryLinks(): HasMany
    {
        return $this->hasMany(RecipeCategoryLink::class);
    }

    /** @return list<string> Its kinds of meal (RecipeCategory values), in the enum's order */
    public function getCategoriesAttribute(): array
    {
        $set = $this->pendingCategories
            ?? ($this->exists ? $this->categoryLinks->map(fn (RecipeCategoryLink $link) => $link->category->value)->all() : []);

        return array_values(array_filter(RecipeCategory::values(), fn ($category) => in_array($category, $set, true)));
    }

    public function setCategoriesAttribute(?array $categories): void
    {
        $this->pendingCategories = array_values(array_unique(array_intersect($categories ?? [], RecipeCategory::values())));
    }

    // The one an older app version shows and sends: the first of its kinds of meal it knows, otherwise a main course.
    // LEGACY(category): for app builds before 60 (one category). Remove once nobody uses them.
    public function getCategoryAttribute(): string
    {
        foreach ($this->categories as $category) {
            if (in_array(RecipeCategory::from($category), RecipeCategory::LEGACY, true)) {
                return $category;
            }
        }

        return RecipeCategory::Main->value;
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

    // The other cookbooks it's shown in (read-only there), besides its own (cookbook_id), with who showed it there
    public function shownIn(): BelongsToMany
    {
        return $this->belongsToMany(Cookbook::class, 'cookbook_recipe')->withPivot('shown_by')->withTimestamps();
    }

    /**
     * Recipes the user may see: in a cookbook of theirs (Cookbook::scopeVisibleTo), or shown in one
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $cookbooks = Cookbook::visibleTo($user)->select('id');

        return $query->where(fn (Builder $query) => $query
            ->whereIn('cookbook_id', $cookbooks)
            ->orWhereHas('shownIn', fn (Builder $shown) => $shown->whereIn('cookbooks.id', Cookbook::visibleTo($user)->select('id'))));
    }

    /**
     * Recipes the user may change (and delete): the ones in their own cookbooks. Where a recipe is only shown, it's
     * read-only.
     */
    public function scopeEditableBy(Builder $query, User $user): Builder
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
