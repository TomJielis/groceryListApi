<?php

namespace App\Jobs;

use App\Models\GroceryList;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Groups item activity into one push: "Tom added 5 items to Weekend" instead of five pushes.
 *
 * The push goes out GROUP_MINUTES after the *last* activity of a user on a list: every activity raises a
 * counter and schedules this job, tagged with a fresh token. When a job runs and a newer activity has
 * replaced its token, it does nothing; the job of that newer activity will send the push.
 */
class SendListActivityPush implements ShouldQueue
{
    use Queueable;

    public const GROUP_MINUTES = 2;

    // Up to this many different products the push names them ("Tom heeft 2× Melk en Brood toegevoegd"), above it
    // it counts them; kept short so it also reads well on a watch
    public const MAX_NAMED = 3;

    /**
     * @param string $type 'items_added' or 'items_checked' (keys in resources/lang/{nl,en}/push.php)
     */
    public function __construct(
        public int $listId,
        public int $actorId,
        public string $type,
        public string $token = '',
    ) {
    }

    /**
     * @param list<array{name: string, quantity: int}> $products The products of this activity, so the push can name
     *                                                          them; without them it counts the activity as one
     */
    public static function record(int $listId, int $actorId, string $type, array $products = []): void
    {
        $key = self::cacheKey($listId, $actorId, $type);
        // Long enough for a whole shopping trip; the job removes the keys when it sends
        $expires = now()->addHours(12);

        // Count first: with QUEUE_CONNECTION=sync the job runs right away (no delay, so no grouping)
        // and must find this activity already counted
        Cache::add($key, 0, $expires);
        Cache::increment($key, max(1, count($products)));
        self::rememberProducts($key, $products, $expires);

        $token = (string) Str::uuid();
        Cache::put($key . ':latest', $token, $expires);

        self::dispatch($listId, $actorId, $type, $token)->delay(now()->addMinutes(self::GROUP_MINUTES));
    }

    // Drops a pending group, e.g. "checked 8 items" when "finished shopping" already went out
    public static function cancel(int $listId, int $actorId, string $type): void
    {
        $key = self::cacheKey($listId, $actorId, $type);
        Cache::forget($key);
        Cache::forget($key . ':latest');
        Cache::forget($key . ':products');
    }

    public function handle(): void
    {
        $key = self::cacheKey($this->listId, $this->actorId, $this->type);

        // A newer activity came in after this one: its job sends the push, two minutes after it
        if (Cache::get($key . ':latest') !== $this->token) {
            return;
        }

        Cache::forget($key . ':latest');
        $count = (int) Cache::pull($key);
        $products = Cache::pull($key . ':products');
        if ($count < 1) {
            return;
        }

        // No logged-in user in a job, so the list's "own lists only" scope must be skipped
        $list = GroceryList::withoutGlobalScopes()->find($this->listId);
        $actor = User::find($this->actorId);
        if (!$list || !$actor) {
            return;
        }

        $replace = ['user' => $actor->name, 'list' => $list->name, 'count' => $count];
        // Every product of the group is known and there are few enough: name them instead of counting.
        // (The products are joined in the language of each member, in SendPushNotification.)
        if (is_array($products) && $products !== [] && count($products) <= self::MAX_NAMED
            && !array_key_exists(self::UNNAMED, $products)) {
            $replace['products'] = collect($products)
                ->map(fn (int $quantity, string $name) => ['name' => $name, 'quantity' => $quantity])
                ->values()
                ->all();
        }

        foreach ($list->memberIds() as $memberId) {
            if ($memberId === $this->actorId) {
                continue;
            }

            SendPushNotification::dispatch($memberId, $this->type, $replace, ['url' => '/list/' . $list->id]);
        }
    }

    // Marks a group with an activity that didn't say its products: that push can only count
    private const UNNAMED = "\0unnamed";

    /**
     * Adds the products to the group, per name ("Melk" twice becomes 2× Melk). Past MAX_NAMED names the push counts
     * anyway, so it stops keeping them there.
     *
     * @param list<array{name: string, quantity: int}> $products
     */
    private static function rememberProducts(string $key, array $products, \DateTimeInterface $expires): void
    {
        $known = Cache::get($key . ':products', []);
        if ($products === []) {
            $known[self::UNNAMED] = 0;
        }
        foreach ($products as $product) {
            if (count($known) > self::MAX_NAMED) {
                break;
            }
            $name = (string) $product['name'];
            $known[$name] = ($known[$name] ?? 0) + max(1, (int) $product['quantity']);
        }
        Cache::put($key . ':products', $known, $expires);
    }

    private static function cacheKey(int $listId, int $actorId, string $type): string
    {
        return "push:{$type}:list:{$listId}:user:{$actorId}";
    }
}
