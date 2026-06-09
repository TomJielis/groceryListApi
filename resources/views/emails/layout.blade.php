<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>@yield('title')</title>
    <style>
        * { box-sizing: border-box; }

        body {
            background-color: #eaede9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 40px 16px 56px;
            color: #334155;
            -webkit-font-smoothing: antialiased;
        }

        .email-container {
            max-width: 600px;
            margin: auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
        }

        .header {
            background-color: #1c2b2b;
            padding: 36px 32px 32px;
        }

        .header .brand-row {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 20px;
        }

        .header .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: #5ebd8a;
            display: inline-block;
            flex-shrink: 0;
        }

        .header .brand {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
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
            padding: 36px 32px 32px;
        }

        .content p {
            font-size: 15px;
            color: #475569;
            line-height: 1.75;
            margin: 0 0 14px;
        }

        .content h2 {
            color: #0f172a;
            font-size: 17px;
            font-weight: 600;
            margin: 32px 0 8px;
        }

        .content h4 {
            color: #334155;
            font-size: 14px;
            font-weight: 600;
            margin: 24px 0 6px;
        }

        .cta-button {
            display: inline-block;
            margin-top: 8px;
            background-color: #5ebd8a;
            color: #ffffff;
            padding: 13px 32px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.01em;
        }

        .tips {
            margin-top: 28px;
            background-color: #f8fafc;
            padding: 20px 22px;
            border-radius: 8px;
            border-left: 3px solid #5ebd8a;
        }

        .tips h3 {
            margin: 0 0 10px;
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
            margin-bottom: 7px;
            font-size: 14px;
            color: #475569;
        }

        ul {
            color: #475569;
            padding-left: 20px;
        }

        li {
            margin-bottom: 6px;
            font-size: 14px;
        }

        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 32px 0;
        }

        .muted {
            color: #94a3b8;
            font-size: 13px;
        }

        .footer {
            background-color: #fafafa;
            border-top: 1px solid #f1f5f9;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            padding: 18px 32px;
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
        <div class="title">@yield('header-title')</div>
    </div>

    <div class="content">
        @yield('content')
    </div>

    <div class="footer">
        @yield('footer')
    </div>

</div>
</body>
</html>
