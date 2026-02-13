# Duplicate Items & Top Items Stats - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Store duplicate grocery list items, return grouped items via API, and add top items statistics to admin dashboard.

**Architecture:** Modify `store()` to always create new records, modify `index()` to return only latest record per name, add new `topItems()` endpoint to AdminStatsController, extend `userDetail()` with per-user top items.

**Tech Stack:** Laravel 11, PHP 8.x, MySQL/SQLite

---

## Task 1: Remove Unused Migration

**Files:**
- Delete: `database/migrations/2026_02_13_111255_add_times_added_to_grocery_list_items_table.php`

**Step 1: Delete the migration file**

```bash
rm database/migrations/2026_02_13_111255_add_times_added_to_grocery_list_items_table.php
```

**Step 2: Verify deletion**

```bash
ls database/migrations/ | grep times_added
```
Expected: No output (file deleted)

**Step 3: Commit**

```bash
git add -A
git commit -m "chore: remove unused times_added migration

Storing duplicate records instead of incrementing counter"
```

---

## Task 2: Modify store() to Always Create New Records

**Files:**
- Modify: `app/Http/Controllers/GroceryListItemController.php:47-72`

**Step 1: Update store method**

Replace the existing `store()` method (lines 47-72) with:

```php
public function store(Request $request): \Illuminate\Http\JsonResponse
{
    $data = $request->all();

    $listItem = GroceryListItem::create([
        'name' => ucfirst($data['name']),
        'quantity' => $data['quantity'] ?? 1,
        'list_id' => $data['list_id'] ?? null,
    ]);

    return response()->json([
        'data' => $listItem,
    ]);
}
```

**Step 2: Verify syntax**

```bash
php -l app/Http/Controllers/GroceryListItemController.php
```
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Http/Controllers/GroceryListItemController.php
git commit -m "feat: always create new record when adding grocery item

Allows duplicate items in database for statistics tracking"
```

---

## Task 3: Modify index() to Return Only Latest Record Per Name

**Files:**
- Modify: `app/Http/Controllers/GroceryListItemController.php:16-45`

**Step 1: Update index method**

Replace the existing `index()` method (lines 16-45) with:

```php
public function index(Request $request)
{
    $offset = $request->get('from');
    $limit = $request->get('till');
    $listId = $request->get('listId');

    // Subquery to get latest item ID per name in each list
    $latestIdsSubquery = GroceryListItem::selectRaw('MAX(id) as id')
        ->when($listId, function ($query) use ($listId) {
            $query->where('list_id', $listId);
        })
        ->groupBy('list_id', 'name');

    $listItems = GroceryListItem::select('grocery_list_items.*')
        ->joinSub($latestIdsSubquery, 'latest', function ($join) {
            $join->on('grocery_list_items.id', '=', 'latest.id');
        })
        ->join('grocery_lists', 'grocery_lists.id', '=', 'grocery_list_items.list_id')
        ->leftJoin('grocery_list_invites', 'grocery_list_invites.grocery_list_id', '=', 'grocery_lists.id')
        ->where(function ($subQuery) {
            $subQuery->where('grocery_lists.created_by', auth()->user()->id)
                ->orWhere(function ($query) {
                    $query->where('grocery_list_invites.user_id', '=', auth()->user()->id)
                        ->where('grocery_list_invites.status', 'accepted');
                });
        });

    if (isset($listId)) {
        $listItems->where('grocery_list_items.list_id', $listId);
    }

    if (isset($offset) && isset($limit)) {
        $listItems->limit($limit)
            ->offset($offset);
    }

    $listItems->groupBy(
        'grocery_list_items.id',
        'grocery_list_items.name',
        'grocery_list_items.quantity',
        'grocery_list_items.checked',
        'grocery_list_items.list_id',
        'grocery_list_items.created_at',
        'grocery_list_items.updated_at',
        'grocery_list_items.unit_price'
    );

    return response()->json([
        'data' => $listItems->get(),
    ]);
}
```

**Step 2: Verify syntax**

```bash
php -l app/Http/Controllers/GroceryListItemController.php
```
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Http/Controllers/GroceryListItemController.php
git commit -m "feat: return only latest record per item name in index

Groups duplicate items, showing only most recent entry per name"
```

---

## Task 4: Modify checked() to Update Latest Record

**Files:**
- Modify: `app/Http/Controllers/GroceryListItemController.php:75-83`

**Step 1: Update checked method**

Replace the existing `checked()` method with:

```php
public function checked(Request $request, GroceryListItem $listItem): \Illuminate\Http\JsonResponse
{
    // Find the latest record with the same name in the same list
    $latestItem = GroceryListItem::where('name', $listItem->name)
        ->where('list_id', $listItem->list_id)
        ->orderByDesc('id')
        ->first();

    if ($latestItem) {
        $latestItem->checked = $request->get('checked', false);
        $latestItem->save();
    }

    return response()->json([
        'data' => $latestItem ?? $listItem,
    ]);
}
```

**Step 2: Verify syntax**

```bash
php -l app/Http/Controllers/GroceryListItemController.php
```
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Http/Controllers/GroceryListItemController.php
git commit -m "feat: checked() updates latest record with same name

Ensures check status applies to the most recent duplicate"
```

---

## Task 5: Add topItems() Method to AdminStatsController

**Files:**
- Modify: `app/Http/Controllers/AdminStatsController.php`

**Step 1: Add topItems method**

Add the following method to `AdminStatsController` (after the `versions()` method, around line 130):

```php
public function topItems(): JsonResponse
{
    $currentMonth = Carbon::now();
    $previousMonth = Carbon::now()->subMonth();

    return response()->json([
        'current_month' => [
            'period' => $currentMonth->format('Y-m'),
            'most_added' => $this->getMostAddedItems($currentMonth),
            'most_checked' => $this->getMostCheckedItems($currentMonth),
        ],
        'previous_month' => [
            'period' => $previousMonth->format('Y-m'),
            'most_added' => $this->getMostAddedItems($previousMonth),
            'most_checked' => $this->getMostCheckedItems($previousMonth),
        ],
    ]);
}

private function getMostAddedItems(Carbon $month, ?array $listIds = null): array
{
    $query = GroceryListItem::selectRaw('LOWER(name) as name, COUNT(*) as count, COUNT(DISTINCT list_id) as lists_count')
        ->whereYear('created_at', $month->year)
        ->whereMonth('created_at', $month->month);

    if ($listIds !== null) {
        $query->whereIn('list_id', $listIds);
    }

    return $query->groupBy(DB::raw('LOWER(name)'))
        ->orderByDesc('count')
        ->limit($listIds !== null ? 5 : 10)
        ->get()
        ->map(function ($item) {
            return [
                'name' => ucfirst($item->name),
                'count' => (int) $item->count,
                'lists_count' => (int) $item->lists_count,
            ];
        })
        ->toArray();
}

private function getMostCheckedItems(Carbon $month, ?array $listIds = null): array
{
    $query = GroceryListItem::selectRaw('LOWER(name) as name, COUNT(*) as count')
        ->where('checked', true)
        ->whereYear('updated_at', $month->year)
        ->whereMonth('updated_at', $month->month);

    if ($listIds !== null) {
        $query->whereIn('list_id', $listIds);
    }

    return $query->groupBy(DB::raw('LOWER(name)'))
        ->orderByDesc('count')
        ->limit($listIds !== null ? 5 : 10)
        ->get()
        ->map(function ($item) {
            return [
                'name' => ucfirst($item->name),
                'count' => (int) $item->count,
            ];
        })
        ->toArray();
}
```

**Step 2: Verify syntax**

```bash
php -l app/Http/Controllers/AdminStatsController.php
```
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Http/Controllers/AdminStatsController.php
git commit -m "feat: add topItems endpoint to AdminStatsController

Shows most added and most checked items globally per month"
```

---

## Task 6: Add Route for topItems

**Files:**
- Modify: `routes/custom/Admin.php`

**Step 1: Add route**

Add the following line after line 11 (after `stats/versions`):

```php
Route::get('stats/top-items', [AdminStatsController::class, 'topItems']);
```

**Step 2: Verify routes**

```bash
php artisan route:list --path=admin
```
Expected: Should show `GET admin/stats/top-items` in the list

**Step 3: Commit**

```bash
git add routes/custom/Admin.php
git commit -m "feat: add route for admin top-items endpoint"
```

---

## Task 7: Extend userDetail() with Top Items

**Files:**
- Modify: `app/Http/Controllers/AdminStatsController.php:165-244`

**Step 1: Update userDetail method**

Replace the return statement in `userDetail()` (starting around line 215) with:

```php
$allAccessibleListIdsArray = $allAccessibleListIds->toArray();

return response()->json([
    'user' => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'created_at' => $user->created_at,
        'email_verified_at' => $user->email_verified_at,
        'terms_version' => $user->accepted_terms_version,
        'last_active' => $lastActive,
    ],
    'lists' => [
        'owned' => $ownedLists->count(),
        'shared_with_user' => $sharedListIds->count(),
        'total_access' => $allAccessibleListIds->count(),
    ],
    'items' => [
        'current_month' => [
            'period' => $currentMonth->format('Y-m'),
            'added' => $currentMonthItems,
            'checked' => $currentMonthChecked,
        ],
        'previous_month' => [
            'period' => $previousMonth->format('Y-m'),
            'added' => $previousMonthItems,
            'checked' => $previousMonthChecked,
        ],
        'change' => $this->calculateChange($currentMonthItems, $previousMonthItems),
    ],
    'top_items' => [
        'current_month' => [
            'most_added' => $this->getMostAddedItems($currentMonth, $allAccessibleListIdsArray),
            'most_checked' => $this->getMostCheckedItems($currentMonth, $allAccessibleListIdsArray),
        ],
        'previous_month' => [
            'most_added' => $this->getMostAddedItems($previousMonth, $allAccessibleListIdsArray),
            'most_checked' => $this->getMostCheckedItems($previousMonth, $allAccessibleListIdsArray),
        ],
    ],
]);
```

**Step 2: Verify syntax**

```bash
php -l app/Http/Controllers/AdminStatsController.php
```
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Http/Controllers/AdminStatsController.php
git commit -m "feat: add top_items to user detail endpoint

Shows most added/checked items per user"
```

---

## Task 8: Final Verification

**Step 1: Run syntax check on all modified files**

```bash
php -l app/Http/Controllers/GroceryListItemController.php
php -l app/Http/Controllers/AdminStatsController.php
```
Expected: `No syntax errors detected` for both

**Step 2: Clear Laravel cache**

```bash
php artisan cache:clear
php artisan route:clear
```

**Step 3: Verify all routes exist**

```bash
php artisan route:list --path=admin
php artisan route:list --path=grocery-list-item
```

**Step 4: Final commit (if any uncommitted changes)**

```bash
git status
```

---

## Frontend Implementation Plan (For Nuxt Agent)

### Nieuwe pagina: `/admin/top-items`

**Benodigde bestanden:**
- Create: `pages/admin/top-items.vue`

**Componenten:**
1. Pagina titel: "Top Items"
2. Maand selector (toggle current/previous month)
3. Twee tabellen naast elkaar:
   - "Meest Toegevoegd" - kolommen: Naam, Aantal, Lijsten
   - "Meest Gecheckt" - kolommen: Naam, Aantal
4. Loading state
5. Error handling

**API call:**
```typescript
const { data } = await useFetch('/api/admin/stats/top-items')
```

**Data mapping:**
- `data.current_month.most_added` → Tabel links
- `data.current_month.most_checked` → Tabel rechts

### User detail uitbreiding: `/admin/users/[id].vue`

**Wijzigingen:**
1. Voeg sectie toe onder bestaande "Items" sectie
2. Titel: "Top Items van deze Gebruiker"
3. Twee kleine lijstjes (top 5):
   - "Meest Toegevoegd"
   - "Meest Gecheckt"

**Data mapping:**
- `data.top_items.current_month.most_added`
- `data.top_items.current_month.most_checked`

### Admin sidebar

**Wijziging in layout/admin.vue of components/AdminSidebar.vue:**

Voeg menu item toe:
```vue
<NuxtLink to="/admin/top-items">
  Top Items
</NuxtLink>
```

Plaats na "Activity" of "Versions" in de navigatie.
