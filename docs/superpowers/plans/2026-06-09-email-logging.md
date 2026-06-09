# Email Logging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Log every outgoing email to the `sent_emails` database table and expose a paginated admin view with detail modal in the Nuxt frontend.

**Architecture:** A `MailService` wrapper replaces all direct `Mail::to()->send()` calls; it reads the rendered Symfony message after a successful send to capture subject and body, and writes a `SentEmail` record. On failure it writes a failed record and re-throws. An `AdminEmailController` serves a filterable, paginated API. The Nuxt admin section gains a full list page plus a dashboard widget, both sharing a detail modal component.

**Tech Stack:** Laravel 12 / PHPUnit / Eloquent / Symfony Mime — Nuxt 4 / Vue 3 / PrimeVue / Tailwind CSS

---

## File Map

### Backend (`groceryListApi`)

| Action | Path | Responsibility |
|---|---|---|
| Create | `database/migrations/2026_06_09_000000_create_sent_emails_table.php` | `sent_emails` schema |
| Create | `database/factories/SentEmailFactory.php` | Test factory |
| Create | `app/Models/SentEmail.php` | Eloquent model + User relation |
| Create | `app/Services/MailService.php` | Wraps mail dispatch, writes DB record on success/failure |
| Create | `app/Http/Controllers/AdminEmailController.php` | `GET /api/admin/emails` |
| Modify | `routes/custom/Admin.php` | Register new route in admin group |
| Modify | `app/Jobs/Users/StoreUserJob.php` | Use MailService |
| Modify | `app/Http/Controllers/AuthController.php` | Use MailService |
| Modify | `app/Http/Controllers/GroceryListController.php` | Use MailService |

### Frontend (`groceryList`)

| Action | Path | Responsibility |
|---|---|---|
| Modify | `composables/useAdminApi.ts` | Add `getEmails()` |
| Modify | `components/DataTable.vue` | Add `rowClickHandler` prop |
| Create | `components/admin/AdminEmailDetailModal.vue` | Shared detail modal |
| Create | `pages/admin/emails/index.vue` | Full email list page |
| Modify | `pages/admin/index.vue` | Recent Emails widget |

---

## Task 1 — Migration

**Files:**
- Create: `database/migrations/2026_06_09_000000_create_sent_emails_table.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_emails', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->text('error_message')->nullable();
            $table->foreignId('triggered_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_emails');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected output includes: `Migrating: 2026_06_09_000000_create_sent_emails_table` then `Migrated`.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_09_000000_create_sent_emails_table.php
git commit -m "feat: add sent_emails migration"
```

---

## Task 2 — SentEmail model

**Files:**
- Create: `app/Models/SentEmail.php`
- Create: `database/factories/SentEmailFactory.php`
- Create: `tests/Unit/SentEmailTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/SentEmailTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SentEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_created_with_fillable_attributes(): void
    {
        $email = SentEmail::create([
            'type' => 'welcome',
            'recipient_email' => 'test@example.com',
            'recipient_name' => 'Test User',
            'subject' => 'Welcome!',
            'body_html' => '<p>Hello</p>',
            'body_text' => 'Hello',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->assertDatabaseHas('sent_emails', ['recipient_email' => 'test@example.com']);
        $this->assertEquals('welcome', $email->type);
    }

    public function test_triggered_by_relation_returns_user(): void
    {
        $user = User::factory()->create();
        $email = SentEmail::create([
            'type' => 'welcome',
            'recipient_email' => 'test@example.com',
            'status' => 'sent',
            'triggered_by_user_id' => $user->id,
            'sent_at' => now(),
        ]);

        $this->assertInstanceOf(User::class, $email->triggeredBy);
        $this->assertEquals($user->id, $email->triggeredBy->id);
    }

    public function test_triggered_by_is_null_when_not_set(): void
    {
        $email = SentEmail::create([
            'type' => 'reset_password',
            'recipient_email' => 'test@example.com',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->assertNull($email->triggeredBy);
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

```bash
php artisan test --filter SentEmailTest
```

Expected: FAIL — class `App\Models\SentEmail` not found.

- [ ] **Step 3: Create the model**

Create `app/Models/SentEmail.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'recipient_email',
        'recipient_name',
        'subject',
        'body_html',
        'body_text',
        'status',
        'error_message',
        'triggered_by_user_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
```

- [ ] **Step 4: Create the factory**

Create `database/factories/SentEmailFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\SentEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

class SentEmailFactory extends Factory
{
    protected $model = SentEmail::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['welcome', 'reset_password', 'grocery_list_invite']),
            'recipient_email' => $this->faker->safeEmail(),
            'recipient_name' => $this->faker->name(),
            'subject' => $this->faker->sentence(),
            'body_html' => '<p>' . $this->faker->paragraph() . '</p>',
            'body_text' => $this->faker->paragraph(),
            'status' => 'sent',
            'triggered_by_user_id' => null,
            'sent_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => 'SMTP connection failed',
            'body_html' => null,
            'body_text' => null,
            'sent_at' => null,
        ]);
    }
}
```

- [ ] **Step 5: Run tests to confirm they pass**

```bash
php artisan test --filter SentEmailTest
```

Expected: 3 tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Models/SentEmail.php database/factories/SentEmailFactory.php tests/Unit/SentEmailTest.php
git commit -m "feat: add SentEmail model and factory"
```

---

## Task 3 — MailService

**Files:**
- Create: `app/Services/MailService.php`
- Create: `tests/Feature/MailServiceTest.php`

`MailService::send()` wraps `Mail::to($email)->send($mailable)`. On success, it reads the rendered subject and body from the returned `Illuminate\Mail\SentMessage` (via forwarded calls to the underlying `Symfony\Component\Mailer\SentMessage` → `getOriginalMessage()` which returns the `Symfony\Component\Mime\Email`). On exception it writes a failed record and re-throws.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/MailServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\SentEmail;
use App\Services\MailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_writes_sent_record_on_success(): void
    {
        Mail::fake();

        $mailable = new class extends Mailable {
            public function build(): static {
                return $this->subject('Hello')->text('emails.welcome');
            }
        };

        app(MailService::class)->send($mailable, 'to@example.com', 'Test User');

        $this->assertDatabaseHas('sent_emails', [
            'recipient_email' => 'to@example.com',
            'recipient_name' => 'Test User',
            'status' => 'sent',
        ]);
    }

    public function test_writes_failed_record_and_rethrows_on_exception(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \Exception('SMTP failure'));

        $mailable = new class extends Mailable {
            public function build(): static {
                return $this->subject('Hello')->text('emails.welcome');
            }
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('SMTP failure');

        app(MailService::class)->send($mailable, 'to@example.com');

        $this->assertDatabaseHas('sent_emails', [
            'recipient_email' => 'to@example.com',
            'status' => 'failed',
            'error_message' => 'SMTP failure',
        ]);
    }

    public function test_type_is_snake_case_class_name(): void
    {
        Mail::fake();

        app(MailService::class)->send(new \App\Mail\Welcome('http://example.com', \App\Models\User::factory()->make()), 'to@example.com');

        $this->assertDatabaseHas('sent_emails', [
            'type' => 'welcome',
        ]);
    }
}
```

- [ ] **Step 2: Run to confirm it fails**

```bash
php artisan test --filter MailServiceTest
```

Expected: FAIL — class `App\Services\MailService` not found.

- [ ] **Step 3: Create the service**

Create `app/Services/MailService.php`:

```php
<?php

namespace App\Services;

use App\Models\SentEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MailService
{
    public function send(Mailable $mailable, string $email, ?string $name = null): void
    {
        $type = Str::snake(class_basename($mailable));

        try {
            $sentMessage = Mail::to($email)->send($mailable);

            $subject = null;
            $bodyHtml = null;
            $bodyText = null;

            if ($sentMessage) {
                // getOriginalMessage() is forwarded via __call to Symfony\Mailer\SentMessage,
                // which returns the Symfony\Mime\Email that was rendered.
                $symfonyEmail = $sentMessage->getOriginalMessage();
                $subject = $symfonyEmail->getSubject();
                $bodyHtml = $symfonyEmail->getHtmlBody();
                $bodyText = $symfonyEmail->getTextBody();
            }

            SentEmail::create([
                'type' => $type,
                'recipient_email' => $email,
                'recipient_name' => $name,
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'status' => 'sent',
                'triggered_by_user_id' => Auth::id(),
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            SentEmail::create([
                'type' => $type,
                'recipient_email' => $email,
                'recipient_name' => $name,
                'subject' => null,
                'body_html' => null,
                'body_text' => null,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'triggered_by_user_id' => Auth::id(),
            ]);

            throw $e;
        }
    }
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
php artisan test --filter MailServiceTest
```

Expected: 3 tests pass. Note: with `Mail::fake()` the `$sentMessage` is null so `subject`/`body_html`/`body_text` will be null — the record is still written with `status = sent`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/MailService.php tests/Feature/MailServiceTest.php
git commit -m "feat: add MailService for email logging"
```

---

## Task 4 — Update mail call sites

**Files:**
- Modify: `app/Jobs/Users/StoreUserJob.php`
- Modify: `app/Http/Controllers/AuthController.php`
- Modify: `app/Http/Controllers/GroceryListController.php`

Read each file in full before editing. The goal: replace every `Mail::to($addr)->send($mail)` call with `app(\App\Services\MailService::class)->send($mail, $addr, $optionalName)`. Keep existing try/catch blocks as-is — they still receive the re-thrown exception.

- [ ] **Step 1: Update StoreUserJob**

In `app/Jobs/Users/StoreUserJob.php`, inside the `handle()` method, replace:

```php
try {
    Mail::to($user->email)
        ->send($mail);

} catch (\Exception $exception) {
    \Log::error($exception->getMessage());
    return response()->json(['message' => 'Email versturen is mislukt'], 500);
}
```

With:

```php
try {
    app(\App\Services\MailService::class)->send($mail, $user->email, $user->name);
} catch (\Exception $exception) {
    \Log::error($exception->getMessage());
    return response()->json(['message' => 'Email versturen is mislukt'], 500);
}
```

Remove the `use Illuminate\Support\Facades\Mail;` import at the top of the file since `Mail` is no longer used directly.

- [ ] **Step 2: Update AuthController**

Read `app/Http/Controllers/AuthController.php` in full. Find every `Mail::to($email)->send($mail)` pattern (there may be more than one — password reset and possibly email verification). For each one, replace:

```php
Mail::to($email)
    ->send($mail);
```

With:

```php
app(\App\Services\MailService::class)->send($mail, $email);
```

Leave surrounding try/catch blocks unchanged. Remove the `use Illuminate\Support\Facades\Mail;` import only if no other Mail facade calls remain in the file.

- [ ] **Step 3: Update GroceryListController**

In `app/Http/Controllers/GroceryListController.php`, replace:

```php
Mail::to($email)->send(new GroceryListInviteMail(auth()->user(), $groceryList, $user, $email));
```

With:

```php
app(\App\Services\MailService::class)->send(
    new GroceryListInviteMail(auth()->user(), $groceryList, $user, $email),
    $email
);
```

Remove `use Illuminate\Support\Facades\Mail;` if it is no longer used directly.

- [ ] **Step 4: Run the full test suite and static analysis**

```bash
composer phpstan && php artisan test
```

Expected: all tests pass, no PHPStan errors.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/Users/StoreUserJob.php app/Http/Controllers/AuthController.php app/Http/Controllers/GroceryListController.php
git commit -m "feat: route all mail dispatch through MailService"
```

---

## Task 5 — AdminEmailController and route

**Files:**
- Create: `app/Http/Controllers/AdminEmailController.php`
- Modify: `routes/custom/Admin.php`
- Create: `tests/Feature/Admin/AdminEmailControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/AdminEmailControllerTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_returns_paginated_emails(): void
    {
        SentEmail::factory()->count(25)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/emails');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);

        $this->assertCount(20, $response->json('data'));
    }

    public function test_filters_by_status(): void
    {
        SentEmail::factory()->count(3)->create(['status' => 'sent']);
        SentEmail::factory()->count(2)->failed()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/emails?status=failed');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_filters_by_type(): void
    {
        SentEmail::factory()->count(3)->create(['type' => 'welcome']);
        SentEmail::factory()->count(2)->create(['type' => 'reset_password']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/emails?type=welcome');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_limit_returns_n_most_recent_without_pagination_meta(): void
    {
        SentEmail::factory()->count(15)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/emails?limit=10');

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertArrayNotHasKey('meta', $response->json());
    }

    public function test_non_admin_receives_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/emails')
            ->assertForbidden();
    }

    public function test_includes_triggered_by_user(): void
    {
        $user = User::factory()->create();
        SentEmail::factory()->create(['triggered_by_user_id' => $user->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/emails');

        $response->assertOk();
        $this->assertNotNull($response->json('data.0.triggered_by'));
        $this->assertEquals($user->name, $response->json('data.0.triggered_by.name'));
    }
}
```

- [ ] **Step 2: Run to confirm they fail**

```bash
php artisan test --filter AdminEmailControllerTest
```

Expected: FAIL — route not found (404).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/AdminEmailController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\SentEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEmailController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SentEmail::with('triggeredBy:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->query('date_to'));
        }

        if ($request->filled('limit')) {
            $emails = $query->limit((int) $request->query('limit'))->get();
            return response()->json(['data' => $emails]);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        return response()->json($query->paginate($perPage));
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/custom/Admin.php`, add the import and route inside the existing group:

```php
<?php

use App\Http\Controllers\AdminEmailController;
use App\Http\Controllers\AdminStatsController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::get('stats/users', [AdminStatsController::class, 'users']);
    Route::get('stats/items', [AdminStatsController::class, 'items']);
    Route::get('stats/lists', [AdminStatsController::class, 'lists']);
    Route::get('stats/activity', [AdminStatsController::class, 'activity']);
    Route::get('stats/versions', [AdminStatsController::class, 'versions']);
    Route::get('stats/top-items', [AdminStatsController::class, 'topItems']);
    Route::get('stats/spend', [AdminStatsController::class, 'spend']);

    Route::get('users', [AdminStatsController::class, 'usersList']);
    Route::get('users/{id}', [AdminStatsController::class, 'userDetail']);
    Route::post('users/{id}/block', [AdminStatsController::class, 'block']);

    Route::get('emails', [AdminEmailController::class, 'index']);
});
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter AdminEmailControllerTest
```

Expected: all 5 tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AdminEmailController.php routes/custom/Admin.php tests/Feature/Admin/AdminEmailControllerTest.php
git commit -m "feat: add AdminEmailController GET /api/admin/emails"
```

---

## Task 6 — Add getEmails() to useAdminApi.ts

**Files:**
- Modify: `composables/useAdminApi.ts` (in `groceryList/`)

- [ ] **Step 1: Add the function and export it**

In `composables/useAdminApi.ts`, add `getEmails` before the `return` statement:

```typescript
async function getEmails(params: {
    status?: string
    type?: string
    date_from?: string
    date_to?: string
    per_page?: number
    limit?: number
} = {}) {
    const query = new URLSearchParams()
    if (params.status) query.set('status', params.status)
    if (params.type) query.set('type', params.type)
    if (params.date_from) query.set('date_from', params.date_from)
    if (params.date_to) query.set('date_to', params.date_to)
    if (params.per_page) query.set('per_page', String(params.per_page))
    if (params.limit) query.set('limit', String(params.limit))

    const qs = query.toString()
    const response = await fetch(`/api/admin/emails${qs ? '?' + qs : ''}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        },
    })

    if (!response.ok) {
        throw new Error(`Failed to fetch emails: ${response.statusText}`)
    }

    return await response.json()
}
```

Add `getEmails` to the return object (after `blockUser`):

```typescript
return {
    getStatsUsers,
    getStatsItems,
    getStatsLists,
    getStatsActivity,
    getStatsVersions,
    getUsers,
    getUserDetail,
    getStatsTopItems,
    getStatsSpend,
    blockUser,
    getEmails,
}
```

- [ ] **Step 2: Commit**

```bash
git add composables/useAdminApi.ts
git commit -m "feat: add getEmails to useAdminApi"
```

---

## Task 7 — Add rowClickHandler to DataTable.vue

The current `DataTable` supports `rowLink` (a function returning a URL) for navigating on row click. We need `rowClickHandler` (a callback) for opening a modal instead. This is a non-breaking addition — existing uses with `rowLink` are unchanged.

**Files:**
- Modify: `components/DataTable.vue`

- [ ] **Step 1: Add prop to the Props interface**

In the `Props` interface (around line 17), add after `rowLinkLabel`:

```typescript
rowClickHandler?: (row: any) => void
```

- [ ] **Step 2: Update withDefaults**

The `rowClickHandler` prop is a function with no default — no change needed to `withDefaults`. It will be `undefined` when not passed.

- [ ] **Step 3: Update the desktop table row**

The desktop `<tr>` (around line 127) becomes:

```html
<tr
  v-for="(row, rowIndex) in displayData"
  :key="rowIndex"
  class="border-b border-surface-200 transition-colors"
  :class="{ 'cursor-pointer hover:bg-surface-50': !!rowClickHandler }"
  @click="rowClickHandler ? rowClickHandler(row) : undefined"
>
```

- [ ] **Step 4: Update the mobile card row**

The mobile card `<div>` (around line 178) becomes:

```html
<div
  v-for="(row, rowIndex) in displayData"
  :key="rowIndex"
  class="py-4"
  :class="{ 'cursor-pointer': !!rowClickHandler }"
  @click="rowClickHandler ? rowClickHandler(row) : undefined"
>
```

Inside the mobile card's primary row div, add a "View" button alongside the existing `rowLink` NuxtLink (around line 199):

```html
<button
  v-if="rowClickHandler"
  @click.stop="rowClickHandler(row)"
  class="text-sm font-medium flex-shrink-0"
>
  {{ i18n.t('admin.details') }}
</button>
```

- [ ] **Step 5: Commit**

```bash
git add components/DataTable.vue
git commit -m "feat: add rowClickHandler prop to DataTable"
```

---

## Task 8 — AdminEmailDetailModal component

**Files:**
- Create: `components/admin/AdminEmailDetailModal.vue`

- [ ] **Step 1: Create the component**

Create `components/admin/AdminEmailDetailModal.vue`:

```vue
<script setup lang="ts">
import Dialog from 'primevue/dialog'

interface EmailRecord {
    id: number
    type: string
    recipient_email: string
    recipient_name: string | null
    subject: string | null
    body_html: string | null
    body_text: string | null
    status: 'sent' | 'failed'
    error_message: string | null
    triggered_by: { id: number; name: string; email: string } | null
    sent_at: string | null
}

interface Props {
    email: EmailRecord | null
    visible: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
    'update:visible': [value: boolean]
}>()

const close = () => emit('update:visible', false)

const iframeRef = ref<HTMLIFrameElement | null>(null)

watch(
    () => [props.email, props.visible] as const,
    ([email, visible]) => {
        if (!visible || !email?.body_html) return
        nextTick(() => {
            const doc = iframeRef.value?.contentDocument
            if (doc) {
                doc.open()
                doc.write(email.body_html!)
                doc.close()
            }
        })
    }
)
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="close"
        modal
        :header="email?.subject ?? 'Email'"
        :style="{ width: '800px', maxWidth: '95vw' }"
        :dismissable-mask="true"
    >
        <div v-if="email" class="flex flex-col gap-4">
            <!-- Metadata grid -->
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm border-b border-surface-200 pb-4">
                <div>
                    <p class="text-[0.65rem] uppercase tracking-[0.14em] text-surface-500 font-medium">Type</p>
                    <span class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium bg-surface-100 text-surface-700">
                        {{ email.type }}
                    </span>
                </div>
                <div>
                    <p class="text-[0.65rem] uppercase tracking-[0.14em] text-surface-500 font-medium">Status</p>
                    <span
                        class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium"
                        :class="email.status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                    >
                        {{ email.status }}
                    </span>
                </div>
                <div>
                    <p class="text-[0.65rem] uppercase tracking-[0.14em] text-surface-500 font-medium">Recipient</p>
                    <p class="mt-1 text-surface-700">
                        {{ email.recipient_name ? `${email.recipient_name} <${email.recipient_email}>` : email.recipient_email }}
                    </p>
                </div>
                <div>
                    <p class="text-[0.65rem] uppercase tracking-[0.14em] text-surface-500 font-medium">Sent at</p>
                    <p class="mt-1 text-surface-700">
                        {{ email.sent_at ? new Date(email.sent_at).toLocaleString() : '—' }}
                    </p>
                </div>
                <div v-if="email.triggered_by">
                    <p class="text-[0.65rem] uppercase tracking-[0.14em] text-surface-500 font-medium">Triggered by</p>
                    <NuxtLink
                        :to="`/admin/users/${email.triggered_by.id}`"
                        class="mt-1 inline-block underline-offset-2 hover:underline"
                        @click="close"
                    >
                        {{ email.triggered_by.name }}
                    </NuxtLink>
                </div>
            </div>

            <!-- Error -->
            <div
                v-if="email.status === 'failed' && email.error_message"
                class="rounded bg-red-50 border border-red-200 p-3 text-sm text-red-700"
            >
                <p class="font-medium mb-1">Error</p>
                <p>{{ email.error_message }}</p>
            </div>

            <!-- HTML body in sandboxed iframe -->
            <div v-if="email.body_html">
                <p class="text-[0.65rem] uppercase tracking-[0.14em] text-surface-500 font-medium mb-2">HTML Body</p>
                <iframe
                    ref="iframeRef"
                    sandbox="allow-same-origin"
                    class="w-full rounded border border-surface-200"
                    style="height: 420px;"
                />
            </div>

            <!-- Plain text fallback -->
            <div v-else-if="email.body_text">
                <p class="text-[0.65rem] uppercase tracking-[0.14em] text-surface-500 font-medium mb-2">Text Body</p>
                <pre class="rounded bg-surface-50 border border-surface-200 p-4 text-sm overflow-auto max-h-96 whitespace-pre-wrap font-mono">{{ email.body_text }}</pre>
            </div>

            <p v-else class="text-sm text-surface-400 italic">No body content available.</p>
        </div>
    </Dialog>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/admin/AdminEmailDetailModal.vue
git commit -m "feat: add AdminEmailDetailModal component"
```

---

## Task 9 — Emails list page

**Files:**
- Create: `pages/admin/emails/index.vue`

- [ ] **Step 1: Create the page**

Create `pages/admin/emails/index.vue`:

```vue
<script setup lang="ts">
import { useAdminApi } from '~/composables/useAdminApi'
import { useI18nStore } from '~/stores/i18n'
import DataTable from '~/components/DataTable.vue'
import AdminEmailDetailModal from '~/components/admin/AdminEmailDetailModal.vue'

definePageMeta({
    middleware: ['auth', 'admin'],
})

const i18n = useI18nStore()
const { getEmails } = useAdminApi()

const loading = ref(true)
const error = ref<string | null>(null)
const emails = ref<any[]>([])
const total = ref(0)

const filterStatus = ref('')
const filterType = ref('')
const filterDateFrom = ref('')
const filterDateTo = ref('')

const selectedEmail = ref<any | null>(null)
const modalVisible = ref(false)

const typeOptions = [
    { label: 'All types', value: '' },
    { label: 'Welcome', value: 'welcome' },
    { label: 'Reset password', value: 'reset_password' },
    { label: 'Grocery list invite', value: 'grocery_list_invite' },
]

const statusOptions = [
    { label: 'All statuses', value: '' },
    { label: 'Sent', value: 'sent' },
    { label: 'Failed', value: 'failed' },
]

const loadEmails = async () => {
    loading.value = true
    error.value = null
    try {
        const data = await getEmails({
            status: filterStatus.value || undefined,
            type: filterType.value || undefined,
            date_from: filterDateFrom.value || undefined,
            date_to: filterDateTo.value || undefined,
        })
        emails.value = data.data || []
        total.value = data.meta?.total ?? emails.value.length
    } catch (e: any) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

onMounted(loadEmails)

const onFilterChange = () => loadEmails()

const openDetail = (row: any) => {
    selectedEmail.value = row
    modalVisible.value = true
}

const emailColumns = [
    {
        key: 'type',
        label: 'Type',
        isPrimary: true,
        badges: (row: any) => [{ text: row.type, color: 'blue' as const }],
    },
    { key: 'recipient_email', label: 'Recipient' },
    { key: 'subject', label: 'Subject', hideOnMobile: true },
    {
        key: 'status',
        label: 'Status',
        badges: (row: any) => [
            { text: row.status, color: row.status === 'sent' ? 'green' as const : 'red' as const },
        ],
    },
    { key: 'sent_at', label: 'Sent at', type: 'datetime' as const, hideOnMobile: true },
    {
        key: 'triggered_by',
        label: 'Triggered by',
        hideOnMobile: true,
        format: (value: any) => value?.name ?? '—',
    },
]
</script>

<template>
    <div class="px-4 py-6">
        <div class="w-full max-w-5xl mx-auto flex flex-col gap-6 pb-16">
            <PageHeader
                back-to="/admin"
                title="Emails"
                :subtitle="`${total} emails`"
            />

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <select
                    v-model="filterType"
                    @change="onFilterChange"
                    class="text-sm border border-surface-200 rounded px-3 py-1.5 bg-white"
                >
                    <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <select
                    v-model="filterStatus"
                    @change="onFilterChange"
                    class="text-sm border border-surface-200 rounded px-3 py-1.5 bg-white"
                >
                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
                <input
                    v-model="filterDateFrom"
                    type="date"
                    @change="onFilterChange"
                    class="text-sm border border-surface-200 rounded px-3 py-1.5"
                />
                <input
                    v-model="filterDateTo"
                    type="date"
                    @change="onFilterChange"
                    class="text-sm border border-surface-200 rounded px-3 py-1.5"
                />
            </div>

            <div v-if="loading" class="text-sm text-surface-400">Loading...</div>
            <div v-else-if="error" class="text-sm text-red-500">{{ error }}</div>
            <DataTable
                v-else
                :columns="emailColumns"
                :data="emails"
                :row-click-handler="openDetail"
                empty-message="No emails found."
            />
        </div>
    </div>

    <AdminEmailDetailModal
        :email="selectedEmail"
        v-model:visible="modalVisible"
    />
</template>
```

- [ ] **Step 2: Commit**

```bash
git add pages/admin/emails/index.vue
git commit -m "feat: add admin emails list page"
```

---

## Task 10 — Dashboard widget and nav link

**Files:**
- Modify: `pages/admin/index.vue`

The admin dashboard (`pages/admin/index.vue`) already uses `DataTable` with `showViewAll`/`viewAllLink` for the users widget (around line 360). Follow that exact pattern for the emails widget.

- [ ] **Step 1: Add imports and refs**

At the top of the `<script setup>` block, add the import:

```typescript
import AdminEmailDetailModal from '~/components/admin/AdminEmailDetailModal.vue'
```

Add to the `useAdminApi()` destructure:

```typescript
const { ..., getEmails } = useAdminApi()
```

Add refs after the existing ones:

```typescript
const recentEmails = ref<any[]>([])
const selectedEmail = ref<any | null>(null)
const emailModalVisible = ref(false)
```

- [ ] **Step 2: Load recent emails in loadData()**

In the `loadData()` function, add `getEmails({ limit: 10 })` to the existing `Promise.all` call. Add the result assignment after the existing ones:

```typescript
const [..., emails] = await Promise.all([
    // ... existing calls unchanged ...
    getEmails({ limit: 10 }),
])
// ... existing assignments unchanged ...
recentEmails.value = emails.data || []
```

- [ ] **Step 3: Add the openEmail handler**

```typescript
const openEmail = (row: any) => {
    selectedEmail.value = row
    emailModalVisible.value = true
}
```

- [ ] **Step 4: Add email columns definition**

```typescript
const emailColumns = [
    { key: 'type', label: 'Type', isPrimary: true, badges: (row: any) => [{ text: row.type, color: 'blue' as const }] },
    { key: 'recipient_email', label: 'Recipient' },
    { key: 'status', label: 'Status', badges: (row: any) => [{ text: row.status, color: row.status === 'sent' ? 'green' as const : 'red' as const }] },
    { key: 'sent_at', label: 'Sent at', type: 'datetime' as const, hideOnMobile: true },
]
```

- [ ] **Step 5: Add the Recent Emails widget to the template**

In the template, after the "Recently active users table" `DataTable` block (around line 370), add:

```html
<!-- Recent Emails -->
<DataTable
    :columns="emailColumns"
    :data="recentEmails"
    title="Recent Emails"
    icon="✉️"
    :show-view-all="true"
    view-all-link="/admin/emails"
    :row-click-handler="openEmail"
    empty-message="No emails sent yet."
/>
```

At the end of the template (before the closing `</div>`), add the modal:

```html
<AdminEmailDetailModal
    :email="selectedEmail"
    v-model:visible="emailModalVisible"
/>
```

- [ ] **Step 6: Add Emails nav link**

The admin pages use `PageHeader` with `back-to="/admin"` for navigation — there is no separate admin sidebar nav. The "Emails" page is already reachable from the dashboard widget's "View all" link. No additional nav change is needed.

If a separate nav link is desired in the future, add it to the admin dashboard's quick-links section following the existing `/admin/top-items` NuxtLink pattern (around line 378 in `pages/admin/index.vue`).

- [ ] **Step 7: Commit**

```bash
git add pages/admin/index.vue
git commit -m "feat: add recent emails widget to admin dashboard"
```

---

## Verification

After all tasks are complete, run:

```bash
# Backend
composer phpstan && php artisan test

# Manual smoke test
# 1. Trigger a grocery list invite — verify a sent_emails row is created
# 2. Visit /admin/emails — verify the list loads with type/status filters
# 3. Click a row — verify the modal opens with rendered HTML body
# 4. Visit /admin — verify the Recent Emails widget shows the last 10
```
