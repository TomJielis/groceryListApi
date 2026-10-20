<?php

namespace App\Jobs;

use App\Models\GroceryList;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Groups item activity into one push: "Tom added 5 items to Weekend" instead of five pushes.
 *
 * The first activity of a user on a list starts a counter and schedules this job GROUP_MINUTES later;
 * everything the same user does on that list in the meantime only raises the counter.
 */
class SendListActivityPush implements ShouldQueue
{
    use Queueable;

    public const GROUP_MINUTES = 2;

    /**
     * @param string $type 'items_added' or 'items_checked' (keys in resources/lang/{nl,en}/push.php)
     */
    public function __construct(
        public int $listId,
        public int $actorId,
        public string $type,
    ) {
    }

    public static function record(int $listId, int $actorId, string $type): void
    {
        $key = self::cacheKey($listId, $actorId, $type);

        // add() only succeeds for the first activity of this group, so the job is scheduled once
        $startsGroup = Cache::add($key, 0, now()->addMinutes(self::GROUP_MINUTES + 30));

        // Count before dispatching: with QUEUE_CONNECTION=sync the job runs right away (no delay, so no
        // grouping) and would otherwise find a count of 0 and send nothing
        Cache::increment($key);

        if ($startsGroup) {
            self::dispatch($listId, $actorId, $type)->delay(now()->addMinutes(self::GROUP_MINUTES));
        }
    }

    // Drops a pending group, e.g. "checked 8 items" when "finished shopping" already went out
    public static function cancel(int $listId, int $actorId, string $type): void
    {
        Cache::forget(self::cacheKey($listId, $actorId, $type));
    }

    public function handle(): void
    {
        $count = (int) Cache::pull(self::cacheKey($this->listId, $this->actorId, $this->type));
        if ($count < 1) {
            return;
        }

        // No logged-in user in a job, so the list's "own lists only" scope must be skipped
        $list = GroceryList::withoutGlobalScopes()->find($this->listId);
        $actor = User::find($this->actorId);
        if (!$list || !$actor) {
            return;
        }

        foreach ($list->memberIds() as $memberId) {
            if ($memberId === $this->actorId) {
                continue;
            }

            SendPushNotification::dispatch(
                $memberId,
                $this->type,
                ['user' => $actor->name, 'list' => $list->name, 'count' => $count],
                ['url' => '/list/' . $list->id],
            );
        }
    }

    private static function cacheKey(int $listId, int $actorId, string $type): string
    {
        return "push:{$type}:list:{$listId}:user:{$actorId}";
    }
}
