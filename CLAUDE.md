# Shoplisto API (Laravel 12)

The API behind the Expo app (`~/projects/GroceryListExpo`) and the Nuxt website (`~/projects/groceryList`). Both read
the JSON exactly as it is: a changed field, key, type or status code breaks a client, so treat every response as a
contract.

## Where code goes

| Kind | Folder | Name | Does |
|---|---|---|---|
| Input | `app/Http/Requests/<Area>/` | `…Request` | Rules, messages in the user's language, typed getters (`listId(): int`, `recipe(): RecipeInput`) |
| Controller | `app/Http/Controllers/` | `…Controller` | Find, authorize, call a service or query, return a resource. ~5–15 lines per method |
| Logic that changes data | `app/Services/` | `…Service` | Transactions, locks, several writes, side effects (pushes, mails) |
| Reading only | `app/Queries/<Area>/` | `…Query` | Statistics, overviews, heavy reads. Never writes |
| Outside world | `app/Services/` | what it is | `MailService`, `RecipeImageStore`, `SupportNotifier` |
| Output | `app/Http/Resources/` | `…Resource` | The exact JSON of a response |
| Data between layers | `app/Data/` | a noun | `final readonly` classes: `RecipeInput`, `StatsMonths` |

- No `$request->get/input/all/query/validate` in a controller: read input through the request's getters.
- A feature's requests share a base request for messages and field names in the user's language (`RecipeFormRequest`,
  `WeekMenuRequest`, `ListFormRequest`). Translations live in `resources/lang/{nl,en}/<feature>.php`.
- Access: a record the user can't see answers 404 (like one that doesn't exist); one they can see but may not change
  answers 403 with the reason in their language.
- A service only where there is real logic; simple saves stay in the controller.
- A query shared by two areas goes to a neutral folder (`app/Queries/Items/TopItemsQuery`), not copied.

## Resources

- Keep the response's existing shape: the `data` wrapper where it has one, `public static $wrap = null;` where not.
- A resource of a model created in this request answers 201 by itself: force 200 in `withResponse` where the endpoint
  answers 200, or set 201 where a re-read model should answer 201.
- Message-only answers (`{"message": ...}`) may stay `response()->json`.
- Every resource/endpoint has its fields fixed in a `tests/Feature/*ResponseShapeTest.php` with
  `assertExactJsonStructure`.

## Changing a controller without changing its answers

1. Before editing, record status + body of every endpoint (also 401/403/404/409/422, nl and en users) in a temporary
   test, with fixed times (`Carbon::setTestNow`).
2. Refactor, record again, compare. Explain or fix every difference. Delete the temporary test.
3. `php artisan test`. Then the website e2e (`npx playwright test` in `~/projects/groceryList`) and the app's Maestro
   flows for the touched area.

## Comments

Short English sentences explaining why, in the style of the existing files.
