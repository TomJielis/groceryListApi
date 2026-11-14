<?php

namespace App\Jobs;

use App\Models\PushToken;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Sends a push notification to every device of a user through Expo's push service.
 * The text is translated in the user's own language: resources/lang/{nl,en}/push.php
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    // Tries before a failing push gives up
    public int $tries = 3;

    /**
     * @param string $type    Key in push.php, e.g. 'list_removed'
     * @param array  $replace Placeholders for the text, e.g. ['user' => 'Tom', 'list' => 'Weekend']
     * @param array  $data    Sent along to the app, e.g. ['url' => '/list/12'] to open that screen on tap
     */
    public function __construct(
        public int $userId,
        public string $type,
        public array $replace = [],
        public array $data = [],
    ) {
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        // The user switched this kind of notification off in the app
        if (!$user || !$user->wantsPush($this->type)) {
            return;
        }

        // A device whose login has expired is logged out: it shouldn't get this account's pushes any more.
        // (Ended logins are already gone: deleting the login deletes its push token.)
        $user->pushTokens()
            ->whereHas('accessToken', fn ($query) => $query->where('expires_at', '<', now()))
            ->delete();

        $tokens = $user->pushTokens()->pluck('token');
        if ($tokens->isEmpty()) {
            return;
        }

        $language = $user->language ?: 'nl';
        $replace = $this->replace;
        $bodyKey = "push.{$this->type}.body";
        // Named products ("2× Melk, Brood en Kaas") are joined in the user's own language, with the text that names them
        if (isset($replace['products'])) {
            $replace['products'] = self::joinProducts($replace['products'], $language);
            $bodyKey = "push.{$this->type}.body_named";
        }
        // With a count, texts can have singular|plural forms ("1 product" / "5 producten")
        $translate = isset($replace['count'])
            ? fn (string $key) => trans_choice($key, $replace['count'], $replace, $language)
            : fn (string $key) => __($key, $replace, $language);
        $title = $translate("push.{$this->type}.title");
        $body = $translate($bodyKey);

        $messages = $tokens->map(fn (string $token) => [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'data' => (object) $this->data,
            'sound' => 'default',
            // Nothing waits for the user any more (invites are links, accepted in one tap): 0 clears a badge left
            // from the email invites
            'badge' => 0,
        ])->values()->all();

        try {
            $response = Http::timeout(15)->acceptJson()->post(self::EXPO_PUSH_URL, $messages)->throw();
        } catch (\Throwable $e) {
            // The queue tries again; on its last try (or run right away, without a queue) it gives up
            if ($this->job !== null && $this->job->getConnectionName() !== 'sync' && $this->attempts() < $this->tries) {
                throw $e;
            }
            report($e);
            return;
        }

        // Expo answers with one ticket per message, in the same order. A token of an uninstalled app
        // (or one with notifications turned off) comes back as DeviceNotRegistered: stop sending to it.
        foreach ($response->json('data', []) as $index => $ticket) {
            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($messages[$index])) {
                PushToken::where('token', $messages[$index]['to'])->delete();
            }
        }
    }

    // Longer product names are cut off, so the push stays readable on a lock screen or a watch
    private const MAX_NAME_LENGTH = 30;

    /**
     * "2× Melk, Brood en Kaas": the quantity only when it's more than one
     *
     * @param list<array{name: string, quantity: int}> $products
     */
    private static function joinProducts(array $products, string $language): string
    {
        $names = array_map(function (array $product) {
            $name = \Illuminate\Support\Str::limit($product['name'], self::MAX_NAME_LENGTH, '…');

            return $product['quantity'] > 1 ? $product['quantity'] . '× ' . $name : $name;
        }, $products);
        $last = array_pop($names);

        return $names === [] ? $last : implode(', ', $names) . ' ' . __('push.and', [], $language) . ' ' . $last;
    }
}
