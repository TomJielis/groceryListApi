<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

// A link to a recipe: whoever opens it reads the recipe and can save their own copy, until it expires or is withdrawn
class RecipeLink extends Model
{
    // How long a link works, like InviteLink
    public const DAYS_VALID = InviteLink::DAYS_VALID;

    protected $fillable = [
        'token',
        'recipe_id',
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

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    // Not expired or withdrawn, and its maker is still in the recipe's cookbook (someone who left can't keep
    // handing it out)
    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $this->createdBy
            && Recipe::editableBy($this->createdBy)->whereKey($this->recipe_id)->exists();
    }

    // On the website, which hands it to the app when that's installed (apple-app-site-association claims /auth/*)
    public function url(): string
    {
        return rtrim((string) config('app.url'), '/') . '/auth/recipe/' . $this->token;
    }

    // The working link of this maker for a recipe, or a new one (sharing it again soon gives the same link)
    public static function forRecipe(Recipe $recipe, User $user): self
    {
        $existing = self::where('recipe_id', $recipe->id)
            ->where('created_by', $user->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now()->addHours(self::DAYS_VALID * 12))
            ->latest('id')
            ->first();

        return $existing ?? self::create([
            'recipe_id' => $recipe->id,
            'token' => Str::random(40),
            'created_by' => $user->id,
            'expires_at' => now()->addDays(self::DAYS_VALID),
        ]);
    }
}
