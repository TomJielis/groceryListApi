# Admin Dashboard - Nuxt Frontend Implementatie Plan

## Overzicht

Dit plan beschrijft de implementatie van een admin dashboard in de bestaande Nuxt applicatie. Het dashboard toont statistieken over gebruikers, items, lijsten en activiteit.

**Belangrijk:** Bekijk eerst de huidige codebase structuur voordat je begint. Volg de bestaande patronen voor componenten, composables, en styling.

---

## Stap 1: Verken de Huidige Codebase

Voordat je begint met implementeren:

1. **Bekijk de mapstructuur** - Identificeer waar componenten, pages, composables en layouts staan
2. **Bekijk bestaande componenten** - Welke UI componenten worden al gebruikt? Is er een component library?
3. **Bekijk auth implementatie** - Hoe werkt authenticatie? Waar wordt de user state opgeslagen?
4. **Bekijk bestaande API calls** - Welk patroon wordt gebruikt voor API communicatie? (useFetch, $fetch, axios, etc.)
5. **Bekijk styling** - Tailwind? CSS modules? SCSS? Volg het bestaande patroon
6. **Bekijk navigatie** - Hoe is het menu/navigatie opgebouwd?

---

## Stap 2: Voeg `is_admin` toe aan User Type/Interface

De API retourneert nu een `is_admin` boolean op de user. Voeg dit toe aan de bestaande user type/interface.

```typescript
interface User {
  // ... bestaande velden
  is_admin: boolean
}
```

---

## Stap 3: Admin Navigatie Item

Voeg een admin link toe aan de navigatie die **alleen zichtbaar is voor ingelogde admin users**.

**Locatie:** Zoek waar het huidige menu/navigatie wordt gerenderd.

**Logica:**
```vue
<template>
  <!-- Alleen tonen als user ingelogd EN admin is -->
  <NuxtLink v-if="user?.is_admin" to="/admin">
    Admin Dashboard
  </NuxtLink>
</template>
```

**Belangrijk:** Plaats dit op een logische plek in de bestaande navigatie. Kijk waar andere menu items staan en volg dat patroon.

---

## Stap 4: Admin API Composable

Maak een composable voor alle admin API calls. Volg het bestaande patroon voor API communicatie in de app.

**Bestand:** `composables/useAdminApi.ts` (of vergelijkbare locatie)

```typescript
export const useAdminApi = () => {
  // Pas aan naar het bestaande API patroon in de app
  const config = useRuntimeConfig()
  const baseUrl = config.public.apiBase // of hoe de API URL geconfigureerd is

  const authHeaders = () => {
    // Gebruik het bestaande auth token patroon
    const token = /* haal token op zoals elders in de app */
    return {
      Authorization: `Bearer ${token}`
    }
  }

  return {
    // Globale stats
    getStatsUsers: () => $fetch(`${baseUrl}/api/admin/stats/users`, { headers: authHeaders() }),
    getStatsItems: () => $fetch(`${baseUrl}/api/admin/stats/items`, { headers: authHeaders() }),
    getStatsLists: () => $fetch(`${baseUrl}/api/admin/stats/lists`, { headers: authHeaders() }),
    getStatsActivity: () => $fetch(`${baseUrl}/api/admin/stats/activity`, { headers: authHeaders() }),
    getStatsVersions: () => $fetch(`${baseUrl}/api/admin/stats/versions`, { headers: authHeaders() }),

    // Per-user
    getUsers: () => $fetch(`${baseUrl}/api/admin/users`, { headers: authHeaders() }),
    getUserDetail: (id: number) => $fetch(`${baseUrl}/api/admin/users/${id}`, { headers: authHeaders() }),
  }
}
```

---

## Stap 5: Herbruikbare Chart Componenten

Maak herbruikbare chart componenten. Installeer eerst een chart library als die nog niet aanwezig is.

**Aanbevolen:** Chart.js met vue-chartjs

```bash
npm install chart.js vue-chartjs
```

### Component: AdminLineChart.vue

Voor dagelijkse activiteit grafieken.

**Locatie:** `components/admin/AdminLineChart.vue` (of vergelijkbare locatie)

```vue
<script setup lang="ts">
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend)

interface Props {
  labels: string[]
  datasets: {
    label: string
    data: number[]
    borderColor?: string
    backgroundColor?: string
  }[]
  title?: string
}

const props = defineProps<Props>()

const chartData = computed(() => ({
  labels: props.labels,
  datasets: props.datasets.map(ds => ({
    ...ds,
    borderColor: ds.borderColor || '#3B82F6',
    backgroundColor: ds.backgroundColor || 'rgba(59, 130, 246, 0.1)',
    tension: 0.3,
    fill: true,
  }))
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top' as const },
    title: { display: !!props.title, text: props.title }
  }
}
</script>

<template>
  <div class="h-64">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>
```

### Component: AdminDoughnutChart.vue

Voor versie verdeling (pie/doughnut chart).

**Locatie:** `components/admin/AdminDoughnutChart.vue`

```vue
<script setup lang="ts">
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

interface Props {
  labels: string[]
  data: number[]
  title?: string
}

const props = defineProps<Props>()

const colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899']

const chartData = computed(() => ({
  labels: props.labels,
  datasets: [{
    data: props.data,
    backgroundColor: colors.slice(0, props.data.length),
  }]
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'right' as const },
    title: { display: !!props.title, text: props.title }
  }
}
</script>

<template>
  <div class="h-64">
    <Doughnut :data="chartData" :options="chartOptions" />
  </div>
</template>
```

### Component: AdminStatsCard.vue

Voor het tonen van een statistiek met vergelijking.

**Locatie:** `components/admin/AdminStatsCard.vue`

```vue
<script setup lang="ts">
interface Props {
  title: string
  value: number | string
  change?: {
    absolute: number
    percentage: number | null
  }
  previousValue?: number | string
}

const props = defineProps<Props>()

const isPositive = computed(() => (props.change?.absolute ?? 0) >= 0)
const changeColor = computed(() => isPositive.value ? 'text-green-600' : 'text-red-600')
const changeIcon = computed(() => isPositive.value ? '↑' : '↓')
</script>

<template>
  <div class="bg-white rounded-lg shadow p-6">
    <h3 class="text-sm font-medium text-gray-500">{{ title }}</h3>
    <div class="mt-2 flex items-baseline">
      <p class="text-3xl font-semibold text-gray-900">{{ value }}</p>
      <p v-if="change && change.percentage !== null" :class="['ml-2 text-sm', changeColor]">
        {{ changeIcon }} {{ Math.abs(change.percentage) }}%
      </p>
    </div>
    <p v-if="previousValue !== undefined" class="mt-1 text-sm text-gray-500">
      Vorige maand: {{ previousValue }}
    </p>
  </div>
</template>
```

---

## Stap 6: Admin Middleware

Maak middleware die controleert of de user admin is.

**Locatie:** `middleware/admin.ts`

```typescript
export default defineNuxtRouteMiddleware((to) => {
  // Pas aan naar hoe user state wordt opgehaald in de app
  const user = /* useAuthUser() of useState('user') of andere methode */

  if (!user.value?.is_admin) {
    return navigateTo('/')
  }
})
```

---

## Stap 7: Admin Pages

### Hoofdpagina: pages/admin/index.vue

```vue
<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'admin'] // pas aan naar bestaande auth middleware naam
})

const { getStatsUsers, getStatsItems, getStatsLists, getStatsActivity, getStatsVersions } = useAdminApi()

const [statsUsers, statsItems, statsLists, statsActivity, statsVersions] = await Promise.all([
  getStatsUsers(),
  getStatsItems(),
  getStatsLists(),
  getStatsActivity(),
  getStatsVersions(),
])

// Prepare activity chart data
const activityLabels = computed(() =>
  statsActivity.current_month.daily.map(d => d.date.split('-')[2]) // dag nummer
)
const activityDatasets = computed(() => [
  {
    label: 'Items toegevoegd',
    data: statsActivity.current_month.daily.map(d => d.items_added),
    borderColor: '#3B82F6',
  },
  {
    label: 'Items afgevinkt',
    data: statsActivity.current_month.daily.map(d => d.items_checked),
    borderColor: '#10B981',
  }
])

// Prepare versions chart data
const versionLabels = computed(() => Object.keys(statsVersions.current_month.breakdown))
const versionData = computed(() =>
  Object.values(statsVersions.current_month.breakdown).map(v => v.count)
)
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-6">Admin Dashboard</h1>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <AdminStatsCard
        title="Totaal gebruikers"
        :value="statsUsers.current_month.value"
        :change="statsUsers.change"
        :previous-value="statsUsers.previous_month.value"
      />
      <AdminStatsCard
        title="Nieuwe gebruikers"
        :value="statsUsers.current_month.breakdown.new_registrations"
      />
      <AdminStatsCard
        title="Actieve gebruikers"
        :value="statsUsers.current_month.breakdown.active"
      />
      <AdminStatsCard
        title="Geverifieerde emails"
        :value="statsUsers.current_month.breakdown.verified_email"
      />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <AdminStatsCard
        title="Items toegevoegd"
        :value="statsItems.current_month.breakdown.added"
        :change="statsItems.change"
        :previous-value="statsItems.previous_month.breakdown.added"
      />
      <AdminStatsCard
        title="Items afgevinkt"
        :value="statsItems.current_month.breakdown.checked"
      />
      <AdminStatsCard
        title="Lijsten aangemaakt"
        :value="statsLists.current_month.breakdown.created"
        :change="statsLists.change"
      />
      <AdminStatsCard
        title="Gedeelde lijsten"
        :value="statsLists.current_month.breakdown.shared"
      />
    </div>

    <!-- Activity Chart -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h2 class="text-lg font-semibold mb-4">Dagelijkse Activiteit - {{ statsActivity.current_month.period }}</h2>
      <AdminLineChart
        :labels="activityLabels"
        :datasets="activityDatasets"
      />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
      <!-- Versions Chart -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Versie Verdeling</h2>
        <p class="text-sm text-gray-500 mb-4">
          {{ statsVersions.current_month.on_latest.percentage }}% op laatste versie ({{ statsVersions.current_month.latest_version }})
        </p>
        <AdminDoughnutChart
          :labels="versionLabels"
          :data="versionData"
        />
      </div>

      <!-- Top Lists -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Top 10 Actieve Lijsten</h2>
        <ul class="divide-y">
          <li
            v-for="(list, index) in statsActivity.current_month.top_lists"
            :key="list.id"
            class="py-2 flex justify-between"
          >
            <span>{{ index + 1 }}. {{ list.name }}</span>
            <span class="text-gray-500">{{ list.items_added }} items</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Link to users page -->
    <NuxtLink to="/admin/users" class="text-blue-600 hover:underline">
      Bekijk alle gebruikers →
    </NuxtLink>
  </div>
</template>
```

### Gebruikers overzicht: pages/admin/users/index.vue

```vue
<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'admin']
})

const { getUsers } = useAdminApi()
const { users, total } = await getUsers()

const formatDate = (date: string | null) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('nl-NL')
}
</script>

<template>
  <div class="p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Gebruikers ({{ total }})</h1>
      <NuxtLink to="/admin" class="text-blue-600 hover:underline">
        ← Terug naar dashboard
      </NuxtLink>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Geregistreerd</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Laatste activiteit</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Lijsten</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Versie</th>
            <th class="px-6 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="user in users" :key="user.id">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <span>{{ user.name }}</span>
                <span v-if="user.email_verified" class="ml-2 text-green-500" title="Geverifieerd">✓</span>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ user.email }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ formatDate(user.created_at) }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ formatDate(user.last_active) }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ user.lists_count }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ user.terms_version || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <NuxtLink :to="`/admin/users/${user.id}`" class="text-blue-600 hover:underline">
                Details
              </NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
```

### Gebruiker detail: pages/admin/users/[id].vue

```vue
<script setup lang="ts">
definePageMeta({
  middleware: ['auth', 'admin']
})

const route = useRoute()
const { getUserDetail } = useAdminApi()

const userId = Number(route.params.id)
const data = await getUserDetail(userId)

const formatDate = (date: string | null) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('nl-NL', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>

<template>
  <div class="p-6">
    <NuxtLink to="/admin/users" class="text-blue-600 hover:underline mb-4 inline-block">
      ← Terug naar gebruikers
    </NuxtLink>

    <h1 class="text-2xl font-bold mb-6">{{ data.user.name }}</h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- User Info -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Gebruiker Info</h2>
        <dl class="divide-y">
          <div class="py-2 flex justify-between">
            <dt class="text-gray-500">Email</dt>
            <dd>{{ data.user.email }}</dd>
          </div>
          <div class="py-2 flex justify-between">
            <dt class="text-gray-500">Geregistreerd</dt>
            <dd>{{ formatDate(data.user.created_at) }}</dd>
          </div>
          <div class="py-2 flex justify-between">
            <dt class="text-gray-500">Email geverifieerd</dt>
            <dd>{{ data.user.email_verified_at ? formatDate(data.user.email_verified_at) : 'Nee' }}</dd>
          </div>
          <div class="py-2 flex justify-between">
            <dt class="text-gray-500">Laatste activiteit</dt>
            <dd>{{ formatDate(data.user.last_active) }}</dd>
          </div>
          <div class="py-2 flex justify-between">
            <dt class="text-gray-500">App versie</dt>
            <dd>{{ data.user.terms_version || '-' }}</dd>
          </div>
        </dl>
      </div>

      <!-- Lists Info -->
      <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Lijsten</h2>
        <dl class="divide-y">
          <div class="py-2 flex justify-between">
            <dt class="text-gray-500">Eigen lijsten</dt>
            <dd>{{ data.lists.owned }}</dd>
          </div>
          <div class="py-2 flex justify-between">
            <dt class="text-gray-500">Gedeeld met gebruiker</dt>
            <dd>{{ data.lists.shared_with_user }}</dd>
          </div>
          <div class="py-2 flex justify-between font-semibold">
            <dt>Totaal toegang</dt>
            <dd>{{ data.lists.total_access }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- Items Activity -->
    <div class="mt-8 bg-white rounded-lg shadow p-6">
      <h2 class="text-lg font-semibold mb-4">Items Activiteit</h2>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <AdminStatsCard
          title="Items toegevoegd (deze maand)"
          :value="data.items.current_month.added"
          :change="data.items.change"
          :previous-value="data.items.previous_month.added"
        />
        <AdminStatsCard
          title="Items afgevinkt (deze maand)"
          :value="data.items.current_month.checked"
          :previous-value="data.items.previous_month.checked"
        />
        <div class="bg-gray-50 rounded-lg p-6">
          <h3 class="text-sm font-medium text-gray-500">Vorige maand ({{ data.items.previous_month.period }})</h3>
          <p class="mt-2 text-lg">
            {{ data.items.previous_month.added }} toegevoegd,
            {{ data.items.previous_month.checked }} afgevinkt
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
```

---

## API Endpoints Reference

### Authenticatie

Alle endpoints vereisen:
- `Authorization: Bearer {token}` header
- User moet `is_admin: true` hebben

Bij geen/ongeldige auth of geen admin rechten: **403 Forbidden**

---

### GET /api/admin/stats/users

Globale gebruikersstatistieken.

**Response:**
```json
{
  "current_month": {
    "period": "2026-02",
    "value": 450,
    "breakdown": {
      "total": 450,
      "new_registrations": 32,
      "active": 180,
      "verified_email": 410
    }
  },
  "previous_month": {
    "period": "2026-01",
    "value": 418,
    "breakdown": {
      "total": 418,
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

---

### GET /api/admin/stats/items

Globale items statistieken.

**Response:**
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

---

### GET /api/admin/stats/lists

Globale lijsten statistieken.

**Response:**
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

---

### GET /api/admin/stats/activity

Dagelijkse activiteit en top lijsten.

**Response:**
```json
{
  "current_month": {
    "period": "2026-02",
    "daily": [
      { "date": "2026-02-01", "items_added": 45, "items_checked": 38 },
      { "date": "2026-02-02", "items_added": 52, "items_checked": 41 }
    ],
    "top_lists": [
      { "id": 12, "name": "Weekboodschappen", "items_added": 89 },
      { "id": 5, "name": "Feestje", "items_added": 67 }
    ]
  },
  "previous_month": {
    "period": "2026-01",
    "daily": [ ... ],
    "top_lists": [ ... ]
  }
}
```

---

### GET /api/admin/stats/versions

Versie verdeling over gebruikers.

**Response:**
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

---

### GET /api/admin/users

Lijst van alle gebruikers.

**Response:**
```json
{
  "users": [
    {
      "id": 1,
      "name": "Jan Jansen",
      "email": "jan@example.com",
      "created_at": "2025-06-15T10:30:00.000000Z",
      "email_verified": true,
      "terms_version": "2.1",
      "last_active": "2026-02-10T14:22:00.000000Z",
      "lists_count": 3
    }
  ],
  "total": 450
}
```

---

### GET /api/admin/users/{id}

Detail statistieken voor een specifieke gebruiker.

**Response:**
```json
{
  "user": {
    "id": 1,
    "name": "Jan Jansen",
    "email": "jan@example.com",
    "created_at": "2025-06-15T10:30:00.000000Z",
    "email_verified_at": "2025-06-15T11:00:00.000000Z",
    "terms_version": "2.1",
    "last_active": "2026-02-10T14:22:00.000000Z"
  },
  "lists": {
    "owned": 3,
    "shared_with_user": 2,
    "total_access": 5
  },
  "items": {
    "current_month": {
      "period": "2026-02",
      "added": 45,
      "checked": 38
    },
    "previous_month": {
      "period": "2026-01",
      "added": 52,
      "checked": 41
    },
    "change": {
      "absolute": -7,
      "percentage": -13.5
    }
  }
}
```

---

## TypeScript Interfaces

```typescript
// Stats response structuur
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
    percentage: number | null
  }
}

interface UsersBreakdown {
  total: number
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

interface ActivityResponse {
  current_month: {
    period: string
    daily: DailyActivity[]
    top_lists: TopList[]
  }
  previous_month: {
    period: string
    daily: DailyActivity[]
    top_lists: TopList[]
  }
}

interface DailyActivity {
  date: string
  items_added: number
  items_checked: number
}

interface TopList {
  id: number
  name: string
  items_added: number
}

interface VersionsResponse {
  current_month: {
    period: string
    latest_version: string
    breakdown: Record<string, { count: number; percentage: number }>
    on_latest: { count: number; percentage: number }
  }
  previous_month: { ... }
  change: { absolute: number; percentage: null }
}

interface UsersListResponse {
  users: UserListItem[]
  total: number
}

interface UserListItem {
  id: number
  name: string
  email: string
  created_at: string
  email_verified: boolean
  terms_version: string | null
  last_active: string | null
  lists_count: number
}

interface UserDetailResponse {
  user: {
    id: number
    name: string
    email: string
    created_at: string
    email_verified_at: string | null
    terms_version: string | null
    last_active: string | null
  }
  lists: {
    owned: number
    shared_with_user: number
    total_access: number
  }
  items: {
    current_month: { period: string; added: number; checked: number }
    previous_month: { period: string; added: number; checked: number }
    change: { absolute: number; percentage: number | null }
  }
}
```

---

## Checklist

- [ ] Codebase structuur verkend
- [ ] `is_admin` toegevoegd aan user type/interface
- [ ] Admin link in navigatie (alleen voor admin users)
- [ ] Chart library geïnstalleerd (chart.js + vue-chartjs)
- [ ] AdminLineChart component gemaakt
- [ ] AdminDoughnutChart component gemaakt
- [ ] AdminStatsCard component gemaakt
- [ ] useAdminApi composable gemaakt
- [ ] Admin middleware gemaakt
- [ ] pages/admin/index.vue gemaakt
- [ ] pages/admin/users/index.vue gemaakt
- [ ] pages/admin/users/[id].vue gemaakt
- [ ] Alles werkt met de API

---

## Belangrijk

1. **Volg bestaande patronen** - Kijk hoe andere componenten, pages en composables zijn opgezet
2. **Hergebruik bestaande componenten** - Gebruik bestaande buttons, cards, tables als die er zijn
3. **Styling consistent** - Volg de bestaande styling conventies (Tailwind classes, kleuren, spacing)
4. **Error handling** - Voeg loading states en error handling toe zoals elders in de app
5. **Responsief** - Zorg dat het dashboard goed werkt op mobile en desktop
