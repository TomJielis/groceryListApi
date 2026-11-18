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

    // What the overview shows of each recipe (RecipeSummaryResource): only the ingredient names
    public const SUMMARY_WITH = ['ingredients:id,recipe_id,name,position', 'favoritedBy:id', 'shownIn:id', 'categoryLinks:id,recipe_id,category'];

    // What the recipe screen shows (RecipeResource)
    public const PRESENTED_WITH = ['ingredients', 'steps', 'createdBy:id,name', 'favoritedBy:id', 'shownIn:id,name', 'cookbook:id,name', 'categoryLinks'];

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
     * Recipes the user may open: the ones in their recipe book (visibleTo), and the ones planned in a week menu they're
     * in, so everyone in the menu can read, cook and shop a planned recipe. Whoever planned it could see it then, so it
     * stays readable while it's in the menu, also when they leave the menu or lose the cookbook (the others can save
     * their own copy). Only for opening one: the recipe book (the overview, search, favorites) stays visibleTo, so a
     * recipe that's only in a menu doesn't show up there, and once it's out of the menu it can't be opened anymore.
     */
    public function scopeReadableBy(Builder $query, User $user): Builder
    {
        $cookbooks = Cookbook::visibleTo($user)->select('id');
        $plannedForUser = WeekMenuEntry::whereIn('week_menu_id', WeekMenu::visibleTo($user)->select('id'))->select('recipe_id');

        return $query->where(fn (Builder $query) => $query
            ->whereIn('cookbook_id', $cookbooks)
            ->orWhereHas('shownIn', fn (Builder $shown) => $shown->whereIn('cookbooks.id', Cookbook::visibleTo($user)->select('id')))
            ->orWhereIn('recipes.id', $plannedForUser));
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
     * An own copy of this recipe in one of the user's cookbooks, with its photo, ingredients and steps (saving a shared
     * recipe link, or a recipe someone planned in a week menu)
     */
    public function copyInto(Cookbook $cookbook, User $user, \App\Services\RecipeImageStore $images): self
    {
        $this->loadMissing(['ingredients', 'steps']);
        $disk = Storage::disk(self::IMAGE_DISK);
        $imagePath = $this->image_path && $disk->exists($this->image_path)
            ? $images->store($disk->get($this->image_path))
            : null;

        return \Illuminate\Support\Facades\DB::transaction(function () use ($cookbook, $user, $imagePath) {
            $copy = self::create([
                ...$this->only([
                    'title', 'description', 'categories', 'servings', 'prep_minutes', 'cook_minutes', 'oven_minutes',
                    'oven_celsius', 'difficulty', 'kcal', 'protein', 'carbs', 'fat', 'source_url',
                ]),
                'cookbook_id' => $cookbook->id,
                'created_by' => $user->id,
                'copied_from_recipe_id' => $this->id,
                'image_path' => $imagePath,
            ]);
            $copy->replaceParts(
                $this->ingredients->map(fn (RecipeIngredient $ingredient) => $ingredient->only(['name', 'amount', 'unit']))->all(),
                $this->steps->pluck('body')->all(),
            );

            return $copy;
        });
    }

    /**
     * Whether the user has this recipe already, so saving it makes no second copy: the recipe itself in a cookbook of
     * theirs ("member"), or a copy of it ("copy")
     */
    public function alreadySavedBy(User $user): ?array
    {
        $own = self::editableBy($user)
            ->where(fn ($query) => $query->whereKey($this->id)->orWhere('copied_from_recipe_id', $this->id))
            // The original itself first
            ->orderByRaw('id = ? desc', [$this->id])
            ->with('cookbook:id,name')
            ->first();

        return $own ? [
            'type' => $own->id === $this->id ? 'member' : 'copy',
            'recipe_id' => $own->id,
            'cookbook_name' => $own->cookbook?->name,
        ] : null;
    }

    // The photo's address (or with $thumb its small copy), on the API's public address when set (API_URL); the ?v=
    // changes with the photo so apps don't show an old one from their cache
    public function imageUrl(bool $thumb = false): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        $path = "/api/recipes/{$this->id}/image";
        $apiUrl = rtrim((string) config('app.api_url'), '/');
        $base = $apiUrl !== '' ? $apiUrl . $path : url($path);

        return $base . '?v=' . substr(md5($this->image_path), 0, 8) . ($thumb ? '&size=thumb' : '');
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
