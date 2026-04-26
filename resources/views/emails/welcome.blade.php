<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ __('welcome.title') }}</title>
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

        .header .subtitle {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.45);
            margin-top: 4px;
        }

        .content {
            padding: 32px 24px;
        }

        .content p {
            font-size: 15px;
            color: #475569;
            line-height: 1.7;
            margin: 0 0 12px;
        }

        .content h2 {
            color: #0f172a;
            font-size: 18px;
            font-weight: 600;
            margin: 32px 0 8px;
        }

        .content h4 {
            color: #334155;
            font-size: 14px;
            font-weight: 600;
            margin: 24px 0 8px;
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

        .tips {
            margin-top: 32px;
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 6px;
            border-left: 3px solid #5ebd8a;
        }

        .tips h3 {
            margin: 0 0 12px;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }

        .tips ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .tips li {
            margin-bottom: 8px;
            font-size: 14px;
            color: #475569;
        }

        ul {
            color: #475569;
            padding-left: 16px;
        }

        li {
            margin-bottom: 6px;
            font-size: 14px;
        }

        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 28px 0;
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
        <div class="title">{{ __('welcome.title') }}</div>
    </div>

    <div class="content">
        <p>{{ __('welcome.hello') }} {{ $user->name }} 👋</p>
        <p>
            {{ __('welcome.welcome') }}<br>
            {{ __('welcome.intro') }}<br>
            {{ __('welcome.share') }}<br>
            {{ __('welcome.start') }}!
        </p>

        <a href="{{ $url }}" class="cta-button">{{ __('welcome.activate_account') }}</a>

        <div class="tips">
            <h3>💡 {{ __('welcome.tips_for_usage') }}</h3>
            <ul>
                <li>✅ {{ __('welcome.tip_1') }}</li>
                <li>👥 {{ __('welcome.tip_2') }}</li>
                <li>📱 {{ __('welcome.tip_3') }}</li>
            </ul>
        </div>

        <div class="divider"></div>

        <h2 style="text-align: center;">{{ __('welcome.add_app_to_start_screen') }}</h2>

        <h4 style="text-align: center;">{{ __('welcome.instruction_safari') }}</h4>
        <ul style="text-align: center; list-style: none; padding: 0;">
            <li>{{ __('welcome.instruction_safari_1') }}</li>
            <li>{{ __('welcome.instruction_safari_2') }}</li>
            <li>{{ __('welcome.instruction_safari_3') }}</li>
            <li>{{ __('welcome.instruction_safari_4') }}</li>
        </ul>

        <h4 style="text-align: center;">{{ __('welcome.instruction_chrome') }}</h4>
        <ul style="text-align: center; list-style: none; padding: 0;">
            <li>{{ __('welcome.instruction_chrome_1') }}</li>
            <li>{{ __('welcome.instruction_chrome_2') }}</li>
            <li>{{ __('welcome.instruction_chrome_3') }}</li>
            <li>{{ __('welcome.instruction_chrome_4') }}</li>
        </ul>
        <p style="text-align: center; margin-top: 16px;">{{ __('welcome.instruction_chrome_5') }}</p>

        <div class="divider"></div>

        <p style="text-align: center; color: #64748b;">
            {{ __('welcome.wish_message') }}<br>
            {{ __('welcome.team') }}
        </p>
    </div>

    <div class="footer">
        {{ __('welcome.footer') }}
    </div>

</div>
</body>
</html>
