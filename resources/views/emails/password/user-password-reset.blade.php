<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ $title }}</title>
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

    <!-- Header with logo and title -->
    <div class="header">
        <div class="logo-wrapper">
            <img src="https://cdn-icons-png.flaticon.com/512/3144/3144456.png" alt="Grocery List Logo"/>
            <div class="title">Grocery List</div>
        </div>
    </div>

    <!-- Content section -->
    <div class="content">
        <br>
        <p style="text-align: center">Hello {{ $user->name }} 👋,</p>
        <p style="text-align: center">
            You requested to reset your password for your Grocery List account.<br>
            Click the button below to set a new password:
        </p>

        <a href="{{ $url }}" class="cta-button">Reset Password</a>

        <p style="margin-top: 24px; text-align: center">
            If you didn’t request this password reset, you can safely ignore this email.
        </p>
    </div>

    <!-- Footer -->
    <div class="footer">
        You are receiving this email because you have an account on the Grocery List website.
    </div>
</div>
</body>
</html>
