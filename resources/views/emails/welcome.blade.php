<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ __('welcome.title') }}</title>
    <style>
        body {
            background-color: #0b1120;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            margin: 0;
            padding: 0;
            color: #e5e7eb;
        }

        .email-container {
            max-width: 600px;
            margin: auto;
            background-color: #111827;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .header {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            padding: 40px 20px 30px;
            text-align: center;
        }

        .header .logo-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .header img {
            width: 48px;
            height: 48px;
            margin-bottom: 10px;
        }

        .header .title {
            font-size: 26px;
            font-weight: 700;
            color: white;
            line-height: 1.2;
            text-align: center;
        }

        .content {
            padding: 30px;
            text-align: center;
        }

        .content h2 {
            color: #ffffff;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .content h4 {
            color: #cbd5e1;
        }

        .content p {
            font-size: 16px;
            color: #cbd5e1;
            line-height: 1.7;
        }

        .cta-button {
            display: inline-block;
            margin-top: 24px;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 0 12px rgba(37, 99, 235, 0.6);
            transition: background 0.2s, transform 0.2s;
        }

        .cta-button:hover {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            transform: translateY(-2px);
        }

        .tips {
            margin-top: 40px;
            background-color: rgba(30, 41, 59, 0.8);
            padding: 20px;
            border-radius: 8px;
            text-align: left;
            border-left: 4px solid #3b82f6;
            color: #cbd5e1;
        }

        .tips h3 {
            margin-top: 0;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #93c5fd;
        }

        .tips ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .tips li {
            margin-bottom: 8px;
            font-size: 15px;
        }

        ul {
            color: #cbd5e1;
        }

        li {
            margin-bottom: 6px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #64748b;
            margin-top: 30px;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body>
<div class="email-container">

    <div class="header">
        <div class="logo-wrapper">
            <img src="https://cdn-icons-png.flaticon.com/512/3144/3144456.png" alt="Boodschappenlijst Logo"/>
            <div class="title">
                {{ __('welcome.title') }}
            </div>
        </div>
    </div>

    <div class="content">
        <br>
        <p style="text-align: center">Hallo {{$user->name}} 👋,</p>
        <p style="text-align: center">
            {{__('welcome.welcome')}}<br>
            {{__('welcome.intro')}}<br>
            {{__('welcome.share')}}<br>
            {{__('welcome.start')}}!
        </p>

        <a href="{{$url}}" class="cta-button">{{__('welcome.activate_account')}}</a>
        <br>

        <div class="tips">
            <h3>💡 {{__('welcome.tips_for_usage')}}</h3>
            <ul>
                <li>✅ {{__('welcome.tip_1')}}</li>
                <li>👥 {{__('welcome.tip_2')}}</li>
                <li>📱 {{__('welcome.tip_3')}}</li>
            </ul>
        </div>

        <div>
            <h2 style="text-align: center; margin-top: 40px;">
                {{__('welcome.add_app_to_start_screen')}}
            </h2>

            <h4 style="text-align: center; margin-top: 40px;">
                {{__('welcome.instruction_safari')}}
            </h4>
            <ul style="text-align: center; padding-left: 0;">
                <li>{{__('welcome.instruction_safari_1')}}</li>
                <li>{{__('welcome.instruction_safari_2')}}</li>
                <li>{{__('welcome.instruction_safari_3')}}</li>
                <li>{{__('welcome.instruction_safari_4')}}</li>
            </ul>

            <h4 style="text-align: center; margin-top: 40px;">
                {{__('welcome.instruction_chrome')}}
            </h4>

            <ul style="text-align: center; padding-left: 0;">
                <li>{{__('welcome.instruction_chrome_1')}}</li>
                <li>{{__('welcome.instruction_chrome_2')}}</li>
                <li>{{__('welcome.instruction_chrome_3')}}</li>
                <li>{{__('welcome.instruction_chrome_4')}}</li>
            </ul>

            <p style="text-align: center; margin-top: 20px;">
                {{__('welcome.instruction_chrome_5')}}
            </p>
        </div>

        <p style="margin-top: 30px; text-align: center;">
            {{__('welcome.wish_message')}}<br>
            {{__('welcome.team')}}
        </p>
    </div>

    <div class="footer">
        {{__('welcome.footer')}}<br>
    </div>
</div>
</body>
</html>