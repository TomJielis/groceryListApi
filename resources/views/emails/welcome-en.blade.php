<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Grocery List</title>
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
        <p style="text-align: center">Hello {{$user->name}}👋,</p>
        <p style="text-align: center">
            Welcome to Grocery List!
            With your new account, you can quickly and easily manage your grocery lists, wherever you are.
            Share lists easily with friends or family, collaborate in real-time and never forget an item again.
            Start organizing today and enjoy a smarter, more convenient way of shopping!
        </p>

        <a href="{{$url}}" class="cta-button">Activate your account</a>
        <br>

        <div class="tips">
            <h3>💡 Usage Tips</h3>
            <ul>
                <li>✅ Set your main list as favorite on the lists page, so you have direct access to it from the dashboard.</li>
                <li>👥 Share your list with friends and collaborate within the app. You can set this up from your lists page.</li>
                <li>📱 Use it on mobile and desktop.</li>
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
                <li>1. Open the app in Safari.</li>
                <li>2. Tap the Share icon (square with arrow, at the bottom).</li>
                <li>3. Scroll down and tap "Add to Home Screen".</li>
                <li>4. Confirm the name and tap "Add".</li>
            </ul>

            <h4 style="text-align: center; margin-top: 40px;">
                Instructions for Android (Chrome or other browsers):
            </h4>

            <ul style="text-align: center; padding-left: 0;">
                <li>Open the app in your browser.</li>
                <li>Tap the three dots (⋮) in the top right corner.</li>
                <li>Select "Add to home screen".</li>
                <li>Confirm the name and tap "Add".</li>
            </ul>

            <p style="text-align: center; margin-top: 20px;">
                You'll now find Grocery List on your home screen — with its own icon!
            </p>
        </div>
        <p style="margin-top: 30px; text-align: center;">
            We wish you a smooth and pleasant shopping experience<br>
            – The Grocery List Team
        </p>
    </div>

    <div class="footer">
        You receive this email because you created an account on www.Tomjielis.com. <br>
    </div>
</div>
</body>
</html>
