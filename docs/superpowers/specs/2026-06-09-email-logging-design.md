# Email Logging — Design Spec
Date: 2026-06-09

## Overview

Log every outgoing email to the database so we can audit what was sent, validate delivery, and later evolve to a queue-based dispatch model when a worker is available.

## Approach

**Log-after-send.** Emails continue to be sent immediately via the existing mail classes. After each send, a `SentEmail` record is written to the database. This gives full visibility without requiring a queue worker.

Interception is handled via Laravel's built-in `MessageSent` (and failure via `MessageSending`) events — a single listener catches all outgoing mail automatically, regardless of which `Mailable` class triggered it. No changes to existing mail classes are required.

## Database

New table: `sent_emails`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | auto-increment |
| `type` | varchar(50) | derived from class name, e.g. `welcome`, `reset_password`, `grocery_list_invite` |
| `recipient_email` | varchar | |
| `recipient_name` | varchar nullable | |
| `subject` | varchar | |
| `body_html` | longtext nullable | rendered HTML body |
| `body_text` | longtext nullable | rendered plain-text body |
| `status` | enum(`sent`, `failed`) | |
| `error_message` | text nullable | populated when status = failed |
| `triggered_by_user_id` | bigint FK → users nullable | `Auth::id()` at time of send; null for unauthenticated flows (e.g. password reset) |
| `sent_at` | timestamp nullable | filled on successful send |
| `created_at` / `updated_at` | timestamps | |

`type` is varchar (not enum) so new mail classes require no migration to register a new type value.

## Backend (groceryListApi)

### New files

**`app/Models/SentEmail.php`**
Eloquent model for the `sent_emails` table. Has a `belongsTo(User::class, 'triggered_by_user_id')` relation. Fillable: all columns except `id` and timestamps.

**`database/migrations/YYYY_MM_DD_create_sent_emails_table.php`**
Creates the `sent_emails` table as described above.

**`app/Listeners/LogSentEmailListener.php`**
Listens to `Illuminate\Mail\Events\MessageSent`. On each event:
1. Reads recipient address + name, subject, HTML body, and text body from the `Symfony\Mime\Email` object on the event.
2. Derives `type` from the mailable class name using snake_case conversion (e.g. `GroceryListInviteMail` → `grocery_list_invite`).
3. Reads `Auth::id()` for `triggered_by_user_id`.
4. Writes a `SentEmail` record with `status = sent` and `sent_at = now()`.

**`app/Services/MailService.php`**
A thin wrapper around `Mail::to()->send()` that catches exceptions and writes a `SentEmail` record with `status = failed` and the exception message as `error_message`. All existing mail dispatch call sites (currently 3: `Welcome`, `ResetPassword`, `GroceryListInviteMail`) are updated to go through this service. On failure, the exception is re-thrown so callers still handle it normally.

**`app/Providers/EventServiceProvider.php`** (or `AppServiceProvider`)
Register `MessageSent → LogSentEmailListener`.

### New API endpoint

`GET /api/admin/emails`
Added to `routes/custom/Admin.php`, protected by existing admin middleware.

Handled by a new `AdminEmailController` (keeps `AdminStatsController` focused on stats).

Query params:
- `type` — filter by mail type
- `status` — filter by `sent` / `failed`
- `date_from` / `date_to` — filter by `sent_at`
- `per_page` — pagination (default 20)
- `limit` — when set (e.g. `?limit=10`), returns the N most recent records without pagination (used by the dashboard widget)

Response: paginated collection of `SentEmail` records with the `triggered_by` user's name/email included.

## Frontend (groceryList)

### Dashboard widget — `/pages/admin/index.vue`

A "Recent Emails" card added to the existing admin dashboard. Shows the last 10 sent emails in a compact table:
- Type (badge)
- Recipient email
- Subject
- Status (badge: green for sent, red for failed)
- Sent at (relative time)

"View all" button at the bottom links to `/admin/emails`.

Clicking any row opens the email detail modal.

### Full list page — `/pages/admin/emails/index.vue`

Protected by the existing `admin` middleware. Mirrors the layout and patterns of `/pages/admin/users/index.vue`.

- PrimeVue `DataTable` with server-side pagination
- Filter bar: type dropdown, status dropdown, date range picker
- Columns: type badge, recipient, subject, status badge, sent at, triggered by (links to user detail)
- Clicking a row opens the email detail modal

### Email detail modal

A PrimeVue `Dialog` component, shared between the dashboard widget and the full list page (extracted as `components/admin/AdminEmailDetailModal.vue`).

Contents:
- Metadata header: type, recipient, subject, status, sent_at, triggered by
- HTML body rendered in a sandboxed `<iframe>` (prevents script execution)
- Plain-text body in a `<pre>` block as fallback
- Error message block (visible only when `status = failed`)

### `composables/useAdminApi.ts`

Add `getEmails(params)` function following the existing pattern of other admin API composable calls.

### Navigation

Add an "Emails" link to the admin navigation so the page is reachable from the dashboard.

## Future: Queue-based dispatch

When a queue worker is available, the architecture can evolve:
1. Add a `pending` status to `sent_emails`.
2. Write the record as `pending` before sending instead of after.
3. Dispatch a `SendEmailJob` that reads the record, sends the mail, and updates the status to `sent` or `failed`.

This spec covers only the log-after-send phase.

## Out of scope

- Retry logic for failed emails
- Email template preview / re-send from admin
- Notification on send failure
