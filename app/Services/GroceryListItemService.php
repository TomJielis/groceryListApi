<?php

namespace App\Services;

use App\Jobs\SendListActivityPush;
use App\Jobs\SendPushNotification;
use App\Models\GroceryList;
use App\Models\GroceryListItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Changing the items of a list. Items are versioned per name: the latest row of a name in a list is the current item.
 * The others on the list get the grouped activity pushes (SendListActivityPush). The caller checked that the user may
 * see the list. Values the app sent are stored as sent (a quantity of "2" stays "2" in the item answered).
 */
class GroceryListItemService
{
    // A new item, with the price it had last time
    public function add(mixed $listId, string $name, mixed $quantity, User $by): GroceryListItem
    {
        $existingListItem = GroceryListItem::where('name', $name)
            ->where('list_id', $listId)
            ->orderByDesc('id')
            ->first();

        $listItem = GroceryListItem::create([
            'name' => ucfirst($name),
            'quantity' => $quantity,
            'list_id' => $listId,
            'unit_price' => $existingListItem['unit_price'] ?? null,
        ]);

        if ($listItem->list_id) {
            SendListActivityPush::record((int) $listItem->list_id, $by->id, 'items_added', [
                ['name' => $listItem->name, 'quantity' => (int) $listItem->quantity],
            ]);
        }

        return $listItem;
    }

    /**
     * Checks the current item of this name off (or on again). Answers that item.
     *
     * @param mixed $checked As sent (true, 1, "1", ...); it's stored and answered that way
     */
    public function check(GroceryListItem $listItem, mixed $checked, User $by): GroceryListItem
    {
        // Find the latest record with the same name in the same list
        $latestItem = GroceryListItem::where('name', $listItem->name)
            ->where('list_id', $listItem->list_id)
            ->orderByDesc('id')
            ->first();

        if ($latestItem) {
            $wasChecked = (bool) $latestItem->checked;
            $latestItem->checked = $checked;
            $latestItem->save();

            if (!$wasChecked && filter_var($checked, FILTER_VALIDATE_BOOLEAN)) {
                $this->notifyChecked((int) $latestItem->list_id, $by);
            }
        }

        return $latestItem ?? $listItem;
    }

    /**
     * @param array{name?: string, quantity?: mixed, unit_price?: mixed} $changes Only the fields that were sent
     */
    public function update(GroceryListItem $listItem, array $changes): GroceryListItem
    {
        if (array_key_exists('name', $changes)) {
            $listItem->name = ucfirst($changes['name']);
        }
        if (array_key_exists('quantity', $changes)) {
            $listItem->quantity = $changes['quantity'];
        }
        if (array_key_exists('unit_price', $changes)) {
            $listItem->unit_price = $changes['unit_price'];
        }

        $listItem->save();

        return $listItem;
    }

    // Less of an item; nothing left deletes it
    public function decrease(GroceryListItem $listItem, int $amount): GroceryListItem
    {
        if ($listItem->quantity - $amount <= 0) {
            $listItem->delete();
        } else {
            $listItem->decrement('quantity', $amount);
        }

        return $listItem;
    }

    /**
     * The ticked products of the week planner, like adding them one by one: one still open on the list gets the
     * quantity added to it, anything else becomes a new item with the price it had last time. The others on the list
     * get one grouped push.
     *
     * @param array<int, array{name: string, quantity: int|string}> $products
     * @return Collection<int, GroceryListItem>
     */
    public function addProducts(int $listId, array $products, User $by): Collection
    {
        $items = DB::transaction(fn () => collect($products)->map(function (array $item) use ($listId) {
            $latestItem = GroceryListItem::where('list_id', $listId)
                ->where('name', $item['name'])
                ->orderByDesc('id')
                ->first();

            if ($latestItem && !$latestItem->checked) {
                $latestItem->increment('quantity', $item['quantity']);

                return $latestItem->fresh();
            }

            return GroceryListItem::create([
                'name' => ucfirst($item['name']),
                'quantity' => $item['quantity'],
                'list_id' => $listId,
                'unit_price' => $latestItem?->unit_price,
            ]);
        }));

        SendListActivityPush::record($listId, $by->id, 'items_added', collect($products)
            ->map(fn (array $item) => ['name' => ucfirst($item['name']), 'quantity' => (int) $item['quantity']])
            ->all());

        return $items->values();
    }

    /**
     * Moves products to another list, e.g. to the list of a shop where they're cheaper. Each goes there like adding
     * it (an open product of that name gets the quantity added, otherwise it's a new row with its price) and leaves
     * its own list like deleting it. Only products on lists the user may see. Answers how many were moved.
     *
     * @param array<int, mixed> $ids
     */
    public function move(array $ids, int $targetId, User $by): int
    {
        $items = $this->visibleAmong($ids)->reject(fn (GroceryListItem $item) => (int) $item->list_id === $targetId);
        DB::transaction(function () use ($items, $targetId) {
            foreach ($items as $item) {
                $latestItem = GroceryListItem::where('list_id', $targetId)
                    ->where('name', $item->name)
                    ->orderByDesc('id')
                    ->first();

                if ($latestItem && !$latestItem->checked) {
                    $latestItem->increment('quantity', $item->quantity);
                } else {
                    GroceryListItem::create([
                        'name' => $item->name,
                        'quantity' => $item->quantity,
                        'list_id' => $targetId,
                        'unit_price' => $item->unit_price ?? $latestItem?->unit_price,
                    ]);
                }
                $item->delete();
            }
        });

        if ($items->isNotEmpty()) {
            SendListActivityPush::record($targetId, $by->id, 'items_added', $items
                ->map(fn (GroceryListItem $item) => ['name' => $item->name, 'quantity' => (int) $item->quantity])
                ->values()
                ->all());
        }

        return $items->count();
    }

    /**
     * Deletes several products at once, like one by one; only on lists the user may see. Answers how many.
     *
     * @param array<int, mixed> $ids
     */
    public function deleteMany(array $ids): int
    {
        $items = $this->visibleAmong($ids);
        GroceryListItem::whereKey($items->modelKeys())->delete();

        return $items->count();
    }

    // The last open item checked off: "finished shopping" right away, which replaces the pending
    // "checked off N items" push. Otherwise the check joins that grouped push.
    private function notifyChecked(int $listId, User $by): void
    {
        $list = GroceryList::withoutGlobalScopes()->find($listId);
        if (!$list) {
            return;
        }

        if ($list->hasOpenItems()) {
            SendListActivityPush::record($list->id, $by->id, 'items_checked');
            return;
        }

        SendListActivityPush::cancel($list->id, $by->id, 'items_checked');
        foreach ($list->memberIds() as $memberId) {
            if ($memberId === $by->id) {
                continue;
            }

            SendPushNotification::dispatch(
                $memberId,
                'list_completed',
                ['user' => $by->name, 'list' => $list->name],
                ['url' => '/list/' . $list->id],
            );
        }
    }

    /**
     * The products among the ids that are on a list the user may see (GroceryList's global scope)
     *
     * @param array<int, mixed> $ids
     * @return EloquentCollection<int, GroceryListItem>
     */
    private function visibleAmong(array $ids): EloquentCollection
    {
        return GroceryListItem::whereKey($ids)
            ->whereIn('list_id', GroceryList::query()->select('id'))
            ->orderBy('id')
            ->get();
    }
}
