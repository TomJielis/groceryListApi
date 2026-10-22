<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * One successful login: who, when, from which address and from the app or the website.
 * Kept indefinitely for now. An IP address is personal data, so a retention period (or clearing
 * ip_address after a while) may be added later.
 */
class SuccessfulLogin extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'client',
        'app_version',
        'user_agent',
        'logged_in_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
        ];
    }

    public static function record(User $user, Request $request): self
    {
        $userAgent = (string) $request->userAgent();

        // The iOS app sends "Boodschappenvriendje/<build> CFNetwork/..."; the website's Nuxt server sends "node"
        if (preg_match('/^Boodschappenvriendje\/([\w.\-]+)/', $userAgent, $match)) {
            $client = 'app';
            $appVersion = $match[1];
        } else {
            $client = $userAgent === 'node' ? 'website' : 'other';
            $appVersion = null;
        }

        return self::create([
            'user_id' => $user->id,
            'ip_address' => (string) $request->ip(),
            'client' => $client,
            'app_version' => $appVersion,
            'user_agent' => $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null,
            'logged_in_at' => now(),
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
