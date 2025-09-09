<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .email-container {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
        }
        .email-header {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: #333333;
            margin-bottom: 1rem;
        }
        .email-content {
            font-size: 1rem;
            color: #555555;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }
        .email-button {
            display: block;
            width: 100%;
            text-align: center;
            background-color: #10b981;
            color: #ffffff;
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .email-button:hover {
            background-color: #059669;
        }
        .email-footer {
            text-align: center;
            font-size: 0.875rem;
            color: #888888;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
<div class="email-container">
    <div class="email-header">{{ $title }}</div>
    <div class="email-content">
        <p>Hello {{ $user->name }},</p>
        <p>You have requested to reset your password for the website {{ config('app.name') }}.
            Please click the button below to proceed:</p>
    </div>
    <a href="{{ $url }}" class="email-button">Reset Password</a>
    <div class="email-footer">
        If you did not request a password reset, please ignore this email.
    </div>
</div>
</body>
</html>