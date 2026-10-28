<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## End-to-end test users

The end-to-end tests of the website (Playwright) and the app (Maestro) make throwaway users through:

```bash
php artisan e2e:user create someone@e2e.test [--name=...] [--language=en|nl] [--admin]   # prints {"id","email","password"}
php artisan e2e:user reset someone@e2e.test       # removes the user's lists and cards, keeps the user
php artisan e2e:user delete 'e2e-web*@e2e.test'   # removes matching users and everything they made
```

It only runs with `APP_ENV=local` or `testing` and only touches addresses ending in `@e2e.test`, so real users are never affected. See the READMEs of the website and the app for running the tests.

## App name

The name in the mails (logo text, texts, sender name) comes from `APP_NAME` in `.env`; the translations in `resources/lang` read it through `config('app.name')`. Changing it also renames the session cookie and the cache prefix, unless `SESSION_COOKIE` and `CACHE_PREFIX` are set: existing sessions and cached values are then not found once.

## Removing old personal data

`php artisan privacy:prune` removes login history, failed login attempts, the email log and support conversations (by their last message) older than `PERSONAL_DATA_RETENTION_MONTHS` (12 by default), as the privacy policy (article 8) promises. It is scheduled daily at 03:30 in `routes/console.php`, which only runs when the server has Laravel's scheduler in its crontab:

```
* * * * * cd /path/to/groceryListApi && php artisan schedule:run >> /dev/null 2>&1
```

Deleting an account (`/api/deactivate`) removes that user's failed login attempts, the mails sent to them, their support questions and their sessions right away.

## Support desk

Signed-in users ask questions in the app (`POST /api/support/tickets`). Each question is a ticket with a conversation; admins answer it in the app's admin (`/api/admin/support/...`). The website only has `/support` with the support address: people without an account (or without the app) mail that.

- **Notifications, push first:** a new question or reply goes as a push to every admin with the app; only when no admin can be reached by push is it mailed to `SUPPORT_NOTIFY_EMAIL`. An answer goes as a push to the person who asked; only when their app can't be reached (no device, or notifications switched off on the phone, which the app reports with `DELETE /api/push-tokens/current`) is it mailed, telling them to open the app. Nobody gets both.
- **No replies by email:** the mails say that replies to them are not read. In the mailbox that sends the app's mail, turn on an auto-reply pointing to the app or the support page, and forward it to your own address, for the few people who reply anyway.
- **Limits:** 15 questions or replies per user per hour. The mail log keeps no conversation text (`support_mail` is a private body type).
