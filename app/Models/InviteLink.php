<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

// A link that invites whoever opens it to a list or a cookbook, until it expires or is withdrawn
class InviteLink extends Model
{
    // How long a link works
    // A week: links are the only way to invite, and some people only open a message after the weekend
    public const DAYS_VALID = 7;

    protected $fillable = [
        'token',
        'grocery_list_id',
        'cookbook_id',
        'created_by',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function groceryList(): BelongsTo
    {
        // The list's global scope only finds lists of the signed-in user; the link is opened by someone else
        return $this->belongsTo(GroceryList::class, 'grocery_list_id')->withoutGlobalScopes();
    }

    public function cookbook(): BelongsTo
    {
        return $this->belongsTo(Cookbook::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    // The link someone opens: on the website, which hands it to the app when that's installed (apple-app-site-association)
    public function url(): string
    {
        return rtrim((string) config('app.url'), '/') . '/auth/invite/' . $this->token;
    }

    /**
     * The working link of this maker for a list or cookbook, or a new one: sharing it again soon gives the same link
     *
     * @param array{grocery_list_id?: int, cookbook_id?: int} $target
     */
    public static function forTarget(array $target, User $user): self
    {
        $existing = self::where($target)
            ->where('created_by', $user->id)
            ->whereNull('revoked_at')
            // Only while it still works for more than half its time: a link shared again shouldn't run out right away
            ->where('expires_at', '>', now()->addHours(self::DAYS_VALID * 12))
            ->latest('id')
            ->first();

        return $existing ?? self::create([
            ...$target,
            'token' => Str::random(40),
            'created_by' => $user->id,
            'expires_at' => now()->addDays(self::DAYS_VALID),
        ]);
    }
}
