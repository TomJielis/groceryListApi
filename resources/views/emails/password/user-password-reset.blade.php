<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ $title }}</title>
    <style>
        body {
            background-color: #edf0ec;
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 32px 16px;
            color: #334155;
        }

        .email-container {
            max-width: 600px;
            margin: auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }

        .header {
            background-color: #1c2b2b;
            padding: 32px 24px;
            text-align: left;
        }

        .header .brand-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .header .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #5ebd8a;
            display: inline-block;
            flex-shrink: 0;
        }

        .header .brand {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.6);
        }

        .header .title {
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.02em;
            margin: 0;
            line-height: 1.3;
        }

        .content {
            padding: 32px 24px;
            text-align: center;
        }

        .content p {
            font-size: 15px;
            color: #475569;
            line-height: 1.7;
            margin: 0 0 12px;
        }

        .cta-button {
            display: inline-block;
            margin-top: 8px;
            background-color: #5ebd8a;
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            padding: 20px 24px;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
<div class="email-container">

    <div class="header">
        <div class="brand-row">
            <span class="dot"></span>
            <span class="brand">TomJielis.com</span>
        </div>
        <div class="title">{{ __('password-reset.grocery_list') }}</div>
    </div>

    <div class="content">
        <p>{{ __('password-reset.hello') }} {{ $user->name }} 👋</p>
        <p>
            {{ __('password-reset.reset_message_1') }}<br>
            {{ __('password-reset.button_reset_password_message') }}
        </p>
        <a href="{{ $url }}" class="cta-button">{{ __('password-reset.reset_password') }}</a>
        <p style="margin-top: 24px; color: #94a3b8; font-size: 13px;">
            {{ __('password-reset.ignore_message') }}
        </p>
    </div>

    <div class="footer">
        {{ __('password-reset.footer') }}
    </div>

</div>
</body>
</html>
