<?php

namespace App\Jobs;

use App\Models\PushToken;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;

/**
 * Sends a push notification to every device of a user through Expo's push service.
 * The text is translated in the user's own language: resources/lang/{nl,en}/push.php
 *
 * With a fallback mail (invites), the mail goes instead when the push reaches no device: the user has none (website
 * only, notifications never allowed), switched this kind off, Expo refuses it for every device, or sending keeps
 * failing. Whether someone saw a push can't be known; Expo only says whether it took it for a device.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    // Tries before a failing push gives up (and the fallback mail goes)
    public int $tries = 3;

    /**
     * @param string $type    Key in push.php, e.g. 'list_shared'
     * @param array  $replace Placeholders for the text, e.g. ['user' => 'Tom', 'list' => 'Weekend']
     * @param array  $data    Sent along to the app, e.g. ['url' => '/list/12'] to open that screen on tap
     * @param Mailable|null $fallbackMail  Sent to $fallbackEmail when the push reaches no device
     */
    public function __construct(
        public int $userId,
        public string $type,
        public array $replace = [],
        public array $data = [],
        public ?Mailable $fallbackMail = null,
        public ?string $fallbackEmail = null,
    ) {
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        // The user switched this kind of notification off in the app
        if (!$user || !$user->wantsPush($this->type)) {
            $this->sendFallbackMail();
            return;
        }

        // A device whose login has expired is logged out: it shouldn't get this account's pushes any more.
        // (Ended logins are already gone: deleting the login deletes its push token.)
        $user->pushTokens()
            ->whereHas('accessToken', fn ($query) => $query->where('expires_at', '<', now()))
            ->delete();

        $tokens = $user->pushTokens()->pluck('token');
        if ($tokens->isEmpty()) {
            $this->sendFallbackMail();
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

        try {
            $response = Http::timeout(15)->acceptJson()->post(self::EXPO_PUSH_URL, $messages)->throw();
        } catch (\Throwable $e) {
            // The queue tries again; on its last try (or run right away, without a queue) the mail goes instead
            if ($this->job !== null && $this->job->getConnectionName() !== 'sync' && $this->attempts() < $this->tries) {
                throw $e;
            }
            report($e);
            $this->sendFallbackMail();
            return;
        }

        // Expo answers with one ticket per message, in the same order. A token of an uninstalled app
        // (or one with notifications turned off) comes back as DeviceNotRegistered: stop sending to it.
        foreach ($response->json('data', []) as $index => $ticket) {
            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($messages[$index])) {
                PushToken::where('token', $messages[$index]['to'])->delete();
            }
        }

        // Taken for at least one device ("ok"): that's the push; otherwise the mail
        if (!collect($response->json('data', []))->contains(fn ($ticket) => ($ticket['status'] ?? null) === 'ok')) {
            $this->sendFallbackMail();
        }
    }

    private function sendFallbackMail(): void
    {
        if ($this->fallbackMail && $this->fallbackEmail) {
            app(MailService::class)->send($this->fallbackMail, $this->fallbackEmail);
        }
    }
}
