# Admin Dashboard API Design

## Overzicht

Admin dashboard endpoints voor het grocery list project. Geeft inzicht in gebruikersstatistieken, activiteit en app-versie verdeling.

**Scope:** Alleen Laravel API endpoints. Nuxt frontend wordt apart geïmplementeerd.

---

## Authenticatie & Autorisatie

### Admin identificatie
- Nieuwe `is_admin` boolean kolom op `users` tabel (default: `false`)
- Admin status handmatig toekennen via database/tinker

### Middleware
- Nieuwe `AdminMiddleware` die checkt of `auth()->user()->is_admin === true`
- Retourneert 403 Forbidden als gebruiker geen admin is
- Alle admin endpoints onder `/api/admin/*` met `auth:sanctum` + `admin` middleware

---

## API Endpoints

Alle endpoints retourneren data voor de huidige maand + vergelijking met vorige maand.

| Endpoint | Beschrijving |
|----------|-------------|
| `GET /api/admin/stats/users` | Gebruikersstatistieken |
| `GET /api/admin/stats/items` | Items statistieken |
| `GET /api/admin/stats/lists` | Lijsten statistieken |
| `GET /api/admin/stats/activity` | Activiteit overview |
| `GET /api/admin/stats/versions` | Versie verdeling |

---

## Response Structuur

Elke endpoint gebruikt dezelfde basis structuur:

```json
{
  "current_month": {
    "period": "2026-02",
    "value": 150,
    "breakdown": { ... }
  },
  "previous_month": {
    "period": "2026-01",
    "value": 120,
    "breakdown": { ... }
  },
  "change": {
    "absolute": 30,
    "percentage": 25.0
  }
}
```

---

## Endpoint Specificaties

### GET /api/admin/stats/users

```json
{
  "current_month": {
    "period": "2026-02",
    "total": 450,
    "breakdown": {
      "new_registrations": 32,
      "active": 180,
      "verified_email": 410
    }
  },
  "previous_month": {
    "period": "2026-01",
    "total": 418,
    "breakdown": {
      "new_registrations": 28,
      "active": 165,
      "verified_email": 385
    }
  },
  "change": {
    "absolute": 32,
    "percentage": 7.7
  }
}
```

**Metrics:**
- `total`: Totaal aantal gebruikers op einde van de maand
- `new_registrations`: Nieuwe registraties in de maand
- `active`: Users met `personal_access_tokens.last_used_at` in afgelopen 30 dagen
- `verified_email`: Users met geverifieerd email adres

---

### GET /api/admin/stats/items

```json
{
  "current_month": {
    "period": "2026-02",
    "value": 1250,
    "breakdown": {
      "added": 1250,
      "checked": 980,
      "avg_per_user": 8.2,
      "avg_per_list": 12.4
    }
  },
  "previous_month": {
    "period": "2026-01",
    "value": 1100,
    "breakdown": {
      "added": 1100,
      "checked": 890,
      "avg_per_user": 7.5,
      "avg_per_list": 11.8
    }
  },
  "change": {
    "absolute": 150,
    "percentage": 13.6
  }
}
```

**Metrics:**
- `added`: Aantal items toegevoegd in de maand
- `checked`: Aantal items afgevinkt in de maand
- `avg_per_user`: Gemiddeld aantal items per actieve gebruiker
- `avg_per_list`: Gemiddeld aantal items per lijst

---

### GET /api/admin/stats/lists

```json
{
  "current_month": {
    "period": "2026-02",
    "value": 45,
    "breakdown": {
      "created": 45,
      "shared": 28,
      "avg_items_per_list": 11.3,
      "avg_members_per_shared_list": 2.4
    }
  },
  "previous_month": {
    "period": "2026-01",
    "value": 38,
    "breakdown": {
      "created": 38,
      "shared": 22,
      "avg_items_per_list": 10.8,
      "avg_members_per_shared_list": 2.2
    }
  },
  "change": {
    "absolute": 7,
    "percentage": 18.4
  }
}
```

**Metrics:**
- `created`: Nieuwe lijsten aangemaakt in de maand
- `shared`: Lijsten met minstens 1 geaccepteerde invite
- `avg_items_per_list`: Gemiddeld aantal items per lijst
- `avg_members_per_shared_list`: Gemiddeld aantal leden per gedeelde lijst

---

### GET /api/admin/stats/activity

```json
{
  "current_month": {
    "period": "2026-02",
    "daily": [
      { "date": "2026-02-01", "items_added": 45, "items_checked": 38 },
      { "date": "2026-02-02", "items_added": 52, "items_checked": 41 },
      ...
    ],
    "top_lists": [
      { "id": 12, "name": "Weekboodschappen", "items_added": 89 },
      { "id": 5, "name": "Feestje", "items_added": 67 },
      ...
    ]
  },
  "previous_month": {
    "period": "2026-01",
    "daily": [ ... ],
    "top_lists": [ ... ]
  }
}
```

**Metrics:**
- `daily`: Array met dagelijkse activiteit voor grafieken
- `top_lists`: Top 10 meest actieve lijsten (meeste items toegevoegd)

---

### GET /api/admin/stats/versions

```json
{
  "current_month": {
    "period": "2026-02",
    "latest_version": "2.1",
    "breakdown": {
      "2.1": { "count": 280, "percentage": 62.2 },
      "2.0": { "count": 120, "percentage": 26.7 },
      "1.9": { "count": 50, "percentage": 11.1 }
    },
    "on_latest": {
      "count": 280,
      "percentage": 62.2
    }
  },
  "previous_month": {
    "period": "2026-01",
    "latest_version": "2.0",
    "breakdown": { ... },
    "on_latest": { ... }
  },
  "change": {
    "absolute": 12.2,
    "percentage": null
  }
}
```

**Metrics:**
- Gebaseerd op `accepted_terms_version` kolom
- `latest_version`: Hoogste versie in het systeem
- `breakdown`: Verdeling per versie
- `on_latest`: Hoeveel users op de laatste versie zitten

---

## Implementatie

### Benodigde bestanden

```
database/migrations/xxxx_add_is_admin_to_users_table.php
app/Http/Middleware/AdminMiddleware.php
app/Http/Controllers/AdminStatsController.php
routes/custom/Admin.php
```

### Migration

```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_admin')->default(false);
});
```

### Middleware registratie

Registreer `AdminMiddleware` als `admin` in `app/Http/Kernel.php` of bootstrap.

### Routes

```php
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/stats/users', [AdminStatsController::class, 'users']);
    Route::get('/stats/items', [AdminStatsController::class, 'items']);
    Route::get('/stats/lists', [AdminStatsController::class, 'lists']);
    Route::get('/stats/activity', [AdminStatsController::class, 'activity']);
    Route::get('/stats/versions', [AdminStatsController::class, 'versions']);
});
```

---

## Frontend URLs (voor Nuxt implementatie)

De Nuxt frontend kan deze endpoints aanroepen:

| Frontend pagina | API endpoint |
|-----------------|--------------|
| `/admin` | Alle endpoints voor dashboard overview |
| `/admin/users` | `GET /api/admin/stats/users` |
| `/admin/items` | `GET /api/admin/stats/items` |
| `/admin/lists` | `GET /api/admin/stats/lists` |
| `/admin/activity` | `GET /api/admin/stats/activity` |
| `/admin/versions` | `GET /api/admin/stats/versions` |

---

## Niet in scope

- Artisan commands voor admin management
- Export functionaliteit
- Real-time updates
- Historische data (alleen huidige + vorige maand)
