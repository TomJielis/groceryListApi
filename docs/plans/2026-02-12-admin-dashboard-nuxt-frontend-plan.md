# Admin Dashboard Nuxt Frontend Plan

## Overzicht

Frontend implementatie voor het admin dashboard in Nuxt. Verbindt met de Laravel API endpoints.

**Voorwaarde:** Laravel API endpoints moeten eerst geïmplementeerd zijn.

---

## API Endpoints (beschikbaar na backend implementatie)

| Endpoint | Beschrijving |
|----------|--------------|
| `GET /api/admin/stats/users` | Gebruikersstatistieken |
| `GET /api/admin/stats/items` | Items statistieken |
| `GET /api/admin/stats/lists` | Lijsten statistieken |
| `GET /api/admin/stats/activity` | Dagelijkse activiteit + top lijsten |
| `GET /api/admin/stats/versions` | Versie verdeling |

Alle endpoints vereisen:
- `Authorization: Bearer {token}` header
- User moet `is_admin: true` hebben
- Retourneren 403 als niet geautoriseerd

---

## Pagina Structuur

```
pages/
  admin/
    index.vue        # Dashboard overview met alle widgets
    users.vue        # Gedetailleerde users statistieken (optioneel)
    activity.vue     # Activiteit grafieken (optioneel)
```

---

## Componenten

### AdminStatsCard.vue
Herbruikbare kaart voor statistieken met:
- Titel
- Huidige waarde (groot)
- Vergelijking met vorige maand (pijl omhoog/omlaag + percentage)
- Kleur indicator (groen = groei, rood = daling)

```vue
<AdminStatsCard
  title="Nieuwe gebruikers"
  :value="32"
  :change="14.3"
  :previous="28"
/>
```

### AdminActivityChart.vue
Lijn grafiek voor dagelijkse activiteit:
- X-as: dagen van de maand
- Y-as: aantal items
- Twee lijnen: items toegevoegd, items afgevinkt

### AdminVersionsPieChart.vue
Taart/donut grafiek voor versie verdeling:
- Segment per versie
- Highlight voor "laatste versie"

### AdminTopLists.vue
Tabel met top 10 meest actieve lijsten:
- Lijst naam
- Aantal items toegevoegd
- Optioneel: eigenaar

---

## Dashboard Layout (index.vue)

```
┌─────────────────────────────────────────────────────────┐
│  Admin Dashboard                                         │
├─────────────────┬─────────────────┬─────────────────────┤
│  Totaal Users   │  Nieuwe Users   │  Actieve Users      │
│     450         │     32 (+14%)   │     180 (+9%)       │
├─────────────────┴─────────────────┴─────────────────────┤
│                                                          │
│  Items Toegevoegd Deze Maand                            │
│  ┌────────────────────────────────────────────────────┐ │
│  │ [Lijn grafiek met dagelijkse data]                 │ │
│  └────────────────────────────────────────────────────┘ │
│                                                          │
├──────────────────────────┬──────────────────────────────┤
│  Versie Verdeling        │  Top 10 Actieve Lijsten      │
│  ┌──────────────────┐    │  1. Weekboodschappen (89)    │
│  │ [Donut chart]    │    │  2. Feestje (67)             │
│  │    62% latest    │    │  3. ...                      │
│  └──────────────────┘    │                              │
└──────────────────────────┴──────────────────────────────┘
```

---

## API Service

```typescript
// composables/useAdminApi.ts

export const useAdminApi = () => {
  const config = useRuntimeConfig()
  const token = useAuthToken()

  const fetchStats = async (endpoint: string) => {
    return await $fetch(`${config.public.apiBase}/api/admin/stats/${endpoint}`, {
      headers: {
        Authorization: `Bearer ${token.value}`
      }
    })
  }

  return {
    getUsers: () => fetchStats('users'),
    getItems: () => fetchStats('items'),
    getLists: () => fetchStats('lists'),
    getActivity: () => fetchStats('activity'),
    getVersions: () => fetchStats('versions')
  }
}
```

---

## Middleware

```typescript
// middleware/admin.ts

export default defineNuxtRouteMiddleware(async (to) => {
  const user = useAuthUser()

  if (!user.value?.is_admin) {
    return navigateTo('/')
  }
})
```

Toepassen op admin pagina's:
```vue
<script setup>
definePageMeta({
  middleware: ['auth', 'admin']
})
</script>
```

---

## Chart Library

Aanbevolen: **Chart.js** met **vue-chartjs** wrapper
- Lichtgewicht
- Goede Vue 3 / Nuxt 3 ondersteuning
- Line charts, pie/donut charts

```bash
npm install chart.js vue-chartjs
```

---

## Implementatie Stappen

1. **Setup**
   - Installeer chart.js en vue-chartjs
   - Maak admin middleware aan
   - Maak useAdminApi composable

2. **Componenten bouwen**
   - AdminStatsCard.vue
   - AdminActivityChart.vue
   - AdminVersionsPieChart.vue
   - AdminTopLists.vue

3. **Dashboard pagina**
   - pages/admin/index.vue
   - Laad alle endpoints parallel
   - Render componenten met data

4. **Styling**
   - Responsive grid layout
   - Loading states
   - Error handling

---

## Response Types (TypeScript)

```typescript
interface StatsResponse<T> {
  current_month: {
    period: string
    value: number
    breakdown: T
  }
  previous_month: {
    period: string
    value: number
    breakdown: T
  }
  change: {
    absolute: number
    percentage: number
  }
}

interface UsersBreakdown {
  new_registrations: number
  active: number
  verified_email: number
}

interface ItemsBreakdown {
  added: number
  checked: number
  avg_per_user: number
  avg_per_list: number
}

interface ListsBreakdown {
  created: number
  shared: number
  avg_items_per_list: number
  avg_members_per_shared_list: number
}

interface ActivityData {
  daily: Array<{
    date: string
    items_added: number
    items_checked: number
  }>
  top_lists: Array<{
    id: number
    name: string
    items_added: number
  }>
}

interface VersionsBreakdown {
  [version: string]: {
    count: number
    percentage: number
  }
}
```

---

## Niet in scope

- Admin user management UI
- Realtime updates (websockets)
- Data export
- Filters/date range pickers
