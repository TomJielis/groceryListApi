<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Limits per IP address and per email address. Requests from this machine itself (the website's server,
     * when it doesn't pass the visitor's address on) share one address, so those only count per email.
     * Login and register answer like their other failures (HTTP 200 with `error`), which the app and the
     * website already show.
     */
    private function configureRateLimiting(): void
    {
        $fromThisMachine = fn (Request $request) => in_array($request->ip(), ['127.0.0.1', '::1'], true);
        $email = fn (Request $request) => is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : '';

        $failedLogin = function (string $message) {
            return fn (Request $request, array $headers) => response()->json(
                ['success' => false, 'error' => $message, 'message' => $message, 'status' => 429],
                200,
                $headers,
            );
        };

        RateLimiter::for('login', function (Request $request) use ($fromThisMachine, $email, $failedLogin) {
            $response = $failedLogin('Too many login attempts. Please try again in a minute.');

            return array_values(array_filter([
                $fromThisMachine($request) ? null : Limit::perMinute(20)->by('login-ip:' . $request->ip())->response($response),
                Limit::perMinute(10)->by('login-email:' . $email($request))->response($response),
            ]));
        });

        RateLimiter::for('register', function (Request $request) use ($fromThisMachine, $email, $failedLogin) {
            $response = $failedLogin($request->input('language') === 'en'
                ? 'Too many attempts. Please try again later.'
                : 'Te veel pogingen. Probeer het later opnieuw.');

            return array_values(array_filter([
                $fromThisMachine($request) ? null : Limit::perHour(10)->by('register-ip:' . $request->ip())->response($response),
                Limit::perHour(5)->by('register-email:' . $email($request))->response($response),
            ]));
        });

        RateLimiter::for('password-reset', function (Request $request) use ($fromThisMachine, $email) {
            $response = fn (Request $request, array $headers) => response()->json(
                ['message' => 'Te veel aanvragen. Probeer het later opnieuw.'],
                429,
                $headers,
            );

            return array_values(array_filter([
                $fromThisMachine($request) ? null : Limit::perHour(10)->by('reset-ip:' . $request->ip())->response($response),
                Limit::perHour(3)->by('reset-email:' . $email($request))->response($response),
            ]));
        });

        // Every invite sends an email, so a signed-in user can't use sharing to send a flood of them
        RateLimiter::for('share', fn (Request $request) => Limit::perHour(30)
            ->by('share-user:' . $request->user()?->id)
            ->response(fn (Request $request, array $headers) => response()->json(
                ['message' => 'Je hebt de afgelopen tijd te veel uitnodigingen verstuurd. Probeer het later opnieuw.'],
                429,
                $headers,
            )));

        // Every support message sends emails, and asking works without an account: limit per address and per
        // sender (the signed-in user, the ticket link or the email address that was filled in)
        RateLimiter::for('support', function (Request $request) use ($fromThisMachine, $email) {
            $response = fn (Request $request, array $headers) => response()->json(
                ['message' => 'Too many messages. Please try again later. / Te veel berichten. Probeer het later opnieuw.'],
                429,
                $headers,
            );
            $sender = auth('sanctum')->id() ?? $request->route('token') ?? $email($request);

            return array_values(array_filter([
                $fromThisMachine($request) ? null : Limit::perHour(20)->by('support-ip:' . $request->ip())->response($response),
                Limit::perHour(15)->by('support-sender:' . $sender)->response($response),
            ]));
        });

        // Reading a conversation by its link: enough for normal use, too little to guess links. Through the website's
        // server every visitor has the same address, so count per link there instead of per address.
        RateLimiter::for('support-read', fn (Request $request) => $fromThisMachine($request)
            ? Limit::perMinute(30)->by('support-read-link:' . $request->route('token'))
            : Limit::perMinute(30)->by('support-read:' . $request->ip()));
    }
}
