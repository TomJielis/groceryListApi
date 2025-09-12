<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Grocery List</title>
    <style>
        body {
            background-color: #f1fdf3;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .header {
            background-color: #22c55e;
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
            color: #111827;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .content p {
            font-size: 16px;
            color: #4b5563;
            line-height: 1.6;
        }

        .cta-button {
            display: inline-block;
            margin-top: 24px;
            background-color: #22c55e;
            color: white;
            padding: 12px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
        }

        .tips {
            margin-top: 40px;
            background-color: #f0fdf4;
            padding: 20px;
            border-radius: 6px;
            text-align: left;
            border-left: 4px solid #22c55e;
            color: #374151;
        }

        .tips h3 {
            margin-top: 0;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
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

        .footer {
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            margin-top: 30px;
            padding: 20px;
        }
    </style>
</head>
<body>
<div class="email-container">

    <!-- Header with perfectly centered logo and title -->
    <div class="header">
        <div class="logo-wrapper">
            <img src="https://cdn-icons-png.flaticon.com/512/3144/3144456.png" alt="Grocery List Logo"/>
            <div class="title">
                Grocery List
            </div>
        </div>
    </div>

    <div class="content">
        <br>
        <p style="text-align: center">Hallo {{$user->name}}👋,</p>
        <p style="text-align: center">
            Welcome to Grocery List!
            With your new account, you can quickly access and manage your shopping lists anytime, anywhere.
            Easily share lists with friends or family, collaborate in real-time, and never forget an item again.
            Start organizing today and enjoy a smarter, more convenient way to shop!
        </p>

        <a href="{{$url}}" class="cta-button">Active your account</a>
        <br>

        <div class="tips">
            <h3>💡 Tips for use</h3>
            <ul>
                <li>✅ Set your main list as a favorite from the lists page, so you can access it directly from the
                    dashboard.
                </li>
                <li>👥 Share your list with friends and collaborate within the app, you can configure this from your
                    lists page
                </li>
                <li>📱 Use it on mobile and desktop</li>
            </ul>
        </div>
        <div>
            <h2 style="text-align: center; margin-top: 40px;">
                Add Grocery List to your Home Screen
            </h2>

            <h4 style="text-align: center; margin-top: 40px;">
                Instructions for iPhone (Safari):
            </h4>
            <ul style="text-align: center; padding-left: 0;">
                <li>1. Open the app in Safari</li>
                <li>2. Tap the Share icon (square with arrow, at the bottom).</li>
                <li>3. Scroll down and tap "Add to Home Screen.</li>
                <li>4. Confirm the name and tap "Add".</li>
            </ul>

            <h4 style="text-align: center; margin-top: 40px;">
                Instructions for Android (Chrome or other browsers):
            </h4>

            <ul style="text-align: center; padding-left: 0;">
                <li>Open the app in your browser</li>
                <li>Tap the three dots (⋮) in the top-right corner.</li>
                <li>Select "Add to Home screen</li>
                <li>Confirm the name and tap "Add".</li>
            </ul>

            <p style="text-align: center; margin-top: 20px;">
                You’ll now find Grocery List on your home screen — with its own icon!
            </p>
        </div>
        <p style="margin-top: 30px; text-align: center;">
            Wishing you a smooth and enjoyable shopping experience<br>
            – The Grocery List Team
        </p>
    </div>

    <div class="footer">
        You are receiving this email because you created an account on www.Tomjielis.com. <br>
    </div>
</div>
</body>
</html>
