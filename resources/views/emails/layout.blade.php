{{--
    Shared layout of the mails, the same look as the auth screens of the app (GroceryListExpo/constants/auth-theme.ts):
    navy header with the brand, and a white sheet with rounded top corners on top of it.

    Mail apps support little CSS, so the layout is built with tables and inline styles (see components/email). The
    <style> below only holds what can't be inline: filling the screen on phones and dark mode. A mail app that ignores
    it still shows the complete mail.
--}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="x-apple-disable-message-reformatting"/>
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no"/>
    <meta name="color-scheme" content="light dark"/>
    <meta name="supported-color-schemes" content="light dark"/>
    <title>@yield('title')</title>
    <style>
        @media only screen and (max-width: 600px) {
            .em-gutter { padding: 0 !important; }
            .em-flat { border-radius: 0 !important; }
        }

        @media (prefers-color-scheme: dark) {
            .em-page { background-color: #0f1514 !important; }
            .em-header { background-color: #2b4441 !important; }
            .em-sheet { background-color: #18201f !important; }
            .em-panel { background-color: #1e2827 !important; border-color: #2b3736 !important; }
            .em-quote { background-color: #1e2827 !important; }
            .em-heading { color: #eef2f1 !important; }
            .em-text { color: #a6b2b0 !important; }
            .em-muted { color: #8e9c9a !important; }
        }
    </style>
</head>
<body class="em-page" style="margin: 0; padding: 0; background-color: #f4f6f3; -webkit-text-size-adjust: 100%;">
<table role="presentation" class="em-page" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f6f3;">
    <tr>
        <td align="center" class="em-gutter" style="padding: 32px 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 520px; border-collapse: separate; font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                <tr>
                    <td class="em-header em-flat" style="background-color: #1c2b2b; border-radius: 16px 16px 0 0; padding: 0;">
                        <div style="padding: 40px 24px 24px;">
                            <div style="margin: 0 0 12px; font-size: 12px; line-height: 16px; font-weight: 600; letter-spacing: 0.6px; text-transform: uppercase; color: #c6cccc;">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 4px; background-color: #5ebd8a; margin-right: 6px;"></span>{{ config('app.name') }}
                            </div>
                            <div style="font-size: 28px; line-height: 34px; font-weight: 700; letter-spacing: -0.5px; color: #ffffff;">@yield('header-title')</div>
                        </div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate;">
                            <tr>
                                <td class="em-sheet" style="background-color: #ffffff; border-radius: 24px 24px 0 0; padding: 32px 24px 24px;">
                                    @yield('content')
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="em-muted" align="center" style="padding: 20px 24px 32px; font-size: 12px; line-height: 18px; color: #64748b;">
                        @yield('footer')
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
