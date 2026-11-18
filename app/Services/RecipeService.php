<?php

namespace App\Services;

use App\Data\RecipeInput;
use App\Jobs\SendListActivityPush;
use App\Models\Cookbook;
use App\Models\GroceryList;
use App\Models\GroceryListItem;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Changing recipes: saving one with its parts and the cookbooks it's shown in, deleting, photos, putting its products
 * on a list, and saving a copy. Who may do it is checked before (RecipeController).
 */
class RecipeService
{
    public function __construct(private RecipeImageStore $images)
    {
    }

    public function create(RecipeInput $input, Cookbook $cookbook, User $by): Recipe
    {
        return DB::transaction(function () use ($input, $cookbook, $by) {
            $recipe = Recipe::create([...$input->fields, 'cookbook_id' => $cookbook->id, 'created_by' => $by->id]);
            $recipe->replaceParts($input->ingredients, $input->steps);
            $this->syncShownIn($recipe, $input->shownIn, $by);

            return $recipe;
        });
    }

    // Also moves it to another cookbook; moved to a cookbook it was shown in, it lives there now
    public function update(Recipe $recipe, RecipeInput $input, User $by): Recipe
    {
        DB::transaction(function () use ($recipe, $input, $by) {
            $recipe->update([...$input->fields, 'cookbook_id' => $input->cookbookId]);
            $recipe->replaceParts($input->ingredients, $input->steps);
            $this->syncShownIn($recipe, $input->shownIn, $by);
            $recipe->shownIn()->detach($input->cookbookId);
        });

        return $recipe;
    }

    /**
     * Only cookbooks of the user's are added to shown_in (the ones it's in already may stay)
     *
     * @param list<int> $userCookbookIds
     */
    public function mayShowIn(?array $shownIn, array $userCookbookIds, ?Recipe $recipe = null): bool
    {
        $allowed = [...$userCookbookIds, ...($recipe ? $this->shownInIds($recipe)->all() : [])];

        return collect($shownIn ?? [])->every(fn (int $id) => in_array($id, $allowed, true));
    }

    public function delete(Recipe $recipe): void
    {
        $recipe->deleteImage();
        $recipe->delete();
    }

    /**
     * Only the ones the user may change, the others are left; answers how many went
     *
     * @param list<int> $recipeIds
     */
    public function deleteMany(array $recipeIds, User $by): int
    {
        $recipes = Recipe::editableBy($by)->whereIn('recipes.id', $recipeIds)->get();
        DB::transaction(fn () => $recipes->each->delete());
        // The photos only once the recipes are gone, so a failed delete doesn't leave recipes without their photo
        $recipes->each->deleteImage();

        return $recipes->count();
    }

    // Favorites are personal: a star in a shared cookbook doesn't change it for the others
    public function setFavorite(Recipe $recipe, User $user, bool $favorite): void
    {
        if ($favorite) {
            $recipe->favoritedBy()->syncWithoutDetaching([$user->id]);
        } else {
            $recipe->favoritedBy()->detach($user->id);
        }
    }

    public function replaceImage(Recipe $recipe, string $bytes): Recipe
    {
        $path = $this->images->store($bytes);
        $recipe->deleteImage();
        $recipe->update(['image_path' => $path]);

        return $recipe;
    }

    public function removeImage(Recipe $recipe): void
    {
        $recipe->deleteImage();
        $recipe->update(['image_path' => null]);
    }

    /**
     * The products checked in the app go on a shopping list, the way adding them by hand does: a product that's still
     * open on the list gets the number added to it, anything else becomes a new item (with the price it had last
     * time). The others on the list get the grouped "added N products" push for the new ones.
     *
     * @param list<array{name: string, quantity: int}> $items
     * @return Collection<int, GroceryListItem>
     */
    public function addToList(GroceryList $list, array $items, User $by): Collection
    {
        return DB::transaction(fn () => collect($items)->map(function (array $item) use ($list, $by) {
            // The latest row of a name is the current item (items are versioned per name), matched like
            // GroceryListItemController::store
            $latestItem = GroceryListItem::where('list_id', $list->id)
                ->where('name', $item['name'])
                ->orderByDesc('id')
                ->first();

            if ($latestItem && !$latestItem->checked) {
                $latestItem->increment('quantity', $item['quantity']);

                return $latestItem->fresh();
            }

            $listItem = GroceryListItem::create([
                'name' => ucfirst($item['name']),
                'quantity' => $item['quantity'],
                'list_id' => $list->id,
                'unit_price' => $latestItem?->unit_price,
            ]);
            SendListActivityPush::record((int) $list->id, $by->id, 'items_added', [
                ['name' => $listItem->name, 'quantity' => (int) $listItem->quantity],
            ]);

            return $listItem;
        })->values());
    }

    /**
     * An own copy of a recipe the user can open but that isn't theirs. One at a time per user and recipe: a double tap
     * mustn't make two copies (the second finds the first). Answers the copy, or what the user has already.
     *
     * @return array{copy: ?Recipe, already: ?array}
     * @throws LockTimeoutException
     */
    public function copy(Recipe $original, Cookbook $cookbook, User $by): array
    {
        return Cache::lock("recipe-copy:{$by->id}:{$original->id}", 30)->block(10, function () use ($original, $cookbook, $by) {
            if ($already = $original->alreadySavedBy($by)) {
                return ['copy' => null, 'already' => $already];
            }

            return ['copy' => $original->copyInto($cookbook, $by, $this->images), 'already' => null];
        });
    }

    /**
     * Shows recipes in one more cookbook, like ticking it under "Ook tonen in" in each recipe's form. Only recipes the
     * user may change; the ones already in that cookbook (its own, or shown there before) are left as they are.
     *
     * @param list<int> $recipeIds
     */
    public function showManyIn(array $recipeIds, Cookbook $cookbook, User $by): int
    {
        $recipes = Recipe::editableBy($by)
            ->whereIn('recipes.id', $recipeIds)
            ->where('recipes.cookbook_id', '!=', $cookbook->id)
            ->whereDoesntHave('shownIn', fn ($query) => $query->whereKey($cookbook->id))
            ->pluck('recipes.id');

        $cookbook->shownRecipes()->attach($recipes->mapWithKeys(fn ($id) => [$id => ['shown_by' => $by->id]])->all());

        return $recipes->count();
    }

    /**
     * Where the recipe is shown besides its own cookbook: the user's other cookbooks they ticked. Cookbooks of others
     * it's shown in stay unless the list leaves them out (taking it out there is fine, putting it in isn't). Without
     * shown_in (older apps, null) nothing changes.
     *
     * @param list<int>|null $shownIn
     */
    private function syncShownIn(Recipe $recipe, ?array $shownIn, User $by): void
    {
        if ($shownIn === null) {
            return;
        }

        $wanted = collect($shownIn)->reject(fn (int $id) => $id === (int) $recipe->cookbook_id)->unique();
        $current = $this->shownInIds($recipe);

        $recipe->shownIn()->detach($current->diff($wanted)->all());
        $recipe->shownIn()->attach(
            $wanted->diff($current)->mapWithKeys(fn ($id) => [$id => ['shown_by' => $by->id]])->all()
        );
    }

    /**
     * @return Collection<int, int>
     */
    private function shownInIds(Recipe $recipe): Collection
    {
        return $recipe->shownIn()->pluck('cookbooks.id')->map(fn ($id) => (int) $id);
    }
}
