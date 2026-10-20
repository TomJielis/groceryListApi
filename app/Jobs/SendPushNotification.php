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

    /**
     * @param string $type    Key in push.php, e.g. 'list_shared'
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
        if (!$user) {
            return;
        }

        $tokens = $user->pushTokens()->pluck('token');
        if ($tokens->isEmpty()) {
            return;
        }

        $language = $user->language ?: 'nl';
        // With a count, texts can have singular|plural forms ("1 product" / "5 producten")
        $translate = isset($this->replace['count'])
            ? fn (string $key) => trans_choice($key, $this->replace['count'], $this->replace, $language)
            : fn (string $key) => __($key, $this->replace, $language);
        $title = $translate("push.{$this->type}.title");
        $body = $translate("push.{$this->type}.body");

        // Every push sets the app icon badge to the open invites, so it is always current (0 clears it)
        $badge = $user->pendingInviteCount();

        $messages = $tokens->map(fn (string $token) => [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'data' => (object) $this->data,
            'sound' => 'default',
            'badge' => $badge,
        ])->values()->all();

        // Throws on a failed request, so the queue retries the job
        $response = Http::timeout(15)->acceptJson()->post(self::EXPO_PUSH_URL, $messages)->throw();

        // Expo answers with one ticket per message, in the same order. A token of an uninstalled app
        // (or one with notifications turned off) comes back as DeviceNotRegistered: stop sending to it.
        foreach ($response->json('data', []) as $index => $ticket) {
            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($messages[$index])) {
                PushToken::where('token', $messages[$index]['to'])->delete();
            }
        }
    }
}
