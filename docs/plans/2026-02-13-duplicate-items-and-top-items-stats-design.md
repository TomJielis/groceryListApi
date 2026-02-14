# Duplicate Items & Top Items Stats - Design Document

## Overzicht

Dit document beschrijft de wijzigingen om:
1. Duplicate grocery list items op te slaan in de database
2. Items gegroepeerd te tonen in de frontend (alleen meest recente per naam)
3. Admin dashboard uit te breiden met "meest toegevoegde/gecheckte items" statistieken

---

## Requirements

1. **Data opslag**: Elke toevoeging = nieuw record (duplicates toegestaan)
2. **Frontend weergave**: Groepeer op naam, toon alleen meest recente record per naam
3. **Check gedrag**: Alleen het laatste record updaten
4. **Admin stats**: Maandelijks, consistent met bestaande structuur
5. **Nieuwe stats**: Meest toegevoegde/gecheckte items per user + globaal

---

## API Wijzigingen

### 1. `POST /api/grocery-list-item/store`

**Huidige logica:** Check of item bestaat → update bestaand record

**Nieuwe logica:** Altijd nieuw record aanmaken

```php
$listItem = GroceryListItem::create([
    'name' => ucfirst($data['name']),
    'quantity' => $data['quantity'] ?? 1,
    'list_id' => $data['list_id'],
]);
```

### 2. `POST /api/grocery-list-item/index`

**Wijziging:** Retourneer per unieke `(name, list_id)` alleen het meest recente record

```php
// Subquery om laatste ID per naam te vinden
$latestIds = GroceryListItem::selectRaw('MAX(id) as id')
    ->where('list_id', $listId)
    ->groupBy('name');

// Alleen die records ophalen
$listItems->whereIn('grocery_list_items.id', $latestIds);
```

### 3. `POST /api/grocery-list-item/{id}/checked`

**Wijziging:** Update het laatste record met dezelfde naam in dezelfde lijst

```php
$latestItem = GroceryListItem::where('name', $listItem->name)
    ->where('list_id', $listItem->list_id)
    ->orderByDesc('id')
    ->first();
$latestItem->checked = $request->get('checked');
$latestItem->save();
```

---

## Nieuwe Admin Endpoint

### `GET /api/admin/stats/top-items`

**Response:**
```json
{
  "current_month": {
    "period": "2026-02",
    "most_added": [
      { "name": "Melk", "count": 145, "lists_count": 32 },
      { "name": "Brood", "count": 98, "lists_count": 28 }
    ],
    "most_checked": [
      { "name": "Melk", "count": 120 },
      { "name": "Eieren", "count": 89 }
    ]
  },
  "previous_month": {
    "period": "2026-01",
    "most_added": [...],
    "most_checked": [...]
  }
}
```

**Query logica:**
- `most_added`: `COUNT(*)` op `grocery_list_items` gegroepeerd op `LOWER(name)`, gefilterd op `created_at`
- `most_checked`: `COUNT(*)` waar `checked = true`, gegroepeerd op `LOWER(name)`, gefilterd op `updated_at`
- Top 10 per categorie
- `lists_count`: Aantal unieke lijsten waar item in voorkomt

---

## Admin User Detail Uitbreiding

### `GET /api/admin/users/{id}`

**Toevoeging aan response:**
```json
{
  "user": { ... },
  "lists": { ... },
  "items": { ... },
  "top_items": {
    "current_month": {
      "most_added": [
        { "name": "Melk", "count": 12 },
        { "name": "Kaas", "count": 8 }
      ],
      "most_checked": [
        { "name": "Melk", "count": 10 },
        { "name": "Brood", "count": 7 }
      ]
    },
    "previous_month": {
      "most_added": [...],
      "most_checked": [...]
    }
  }
}
```

**Query logica:**
- Zelfde als globaal, maar gefilterd op user's accessible lists
- Top 5 per categorie

---

## Database Wijzigingen

### Migration te verwijderen

De uncommitted migration `add_times_added_to_grocery_list_items_table.php` is niet meer nodig.

### Optionele index

Voor betere query performance:
```php
$table->index(['list_id', 'name', 'created_at']);
```

---

## Route Wijzigingen

In `routes/custom/Admin.php`:
```php
Route::get('stats/top-items', [AdminStatsController::class, 'topItems']);
```

---

## Frontend Plan (Nuxt)

### Bestaande grocery list pagina
- Geen wijzigingen nodig - API stuurt al gegroepeerde data

### Nieuwe pagina: `/admin/top-items`
- Tabel "Meest toegevoegd" (naam, count, lists_count)
- Tabel "Meest gecheckt" (naam, count)
- Toggle huidige/vorige maand
- Endpoint: `GET /api/admin/stats/top-items`

### User detail uitbreiding: `/admin/users/:id`
- Sectie toevoegen: "Top items van deze gebruiker"
- Twee lijstjes: meest toegevoegd + meest gecheckt
- Data zit in bestaande endpoint

### Admin sidebar
- Menu item: "Top Items" naar `/admin/top-items`

---

## Samenvatting Endpoints

| Endpoint | Wijziging |
|----------|-----------|
| `POST /api/grocery-list-item/store` | Altijd nieuw record |
| `POST /api/grocery-list-item/index` | Retourneer alleen laatste per naam |
| `POST /api/grocery-list-item/{id}/checked` | Update laatste record met die naam |
| `GET /api/admin/stats/top-items` | **Nieuw** |
| `GET /api/admin/users/{id}` | Uitgebreid met `top_items` |
