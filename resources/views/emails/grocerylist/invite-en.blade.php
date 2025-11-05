<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Grocery List Invitation</title>
    <style>
        body {
            background-color: #0b1120;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            margin: 0;
            padding: 0;
            color: #e5e7eb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .email-container {
            max-width: 600px;
            margin: auto;
            background-color: #111827;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .header {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            padding: 40px 20px 30px;
            text-align: center;
            width: 100%;
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
            width: 100%;
        }
        .content h2 {
            color: #ffffff;
            font-size: 22px;
            margin-bottom: 10px;
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
        .footer {
            text-align: center;
            font-size: 12px;
            color: #64748b;
            margin-top: 30px;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            width: 100%;
        }
    </style>
</head>
<body>
<div class="email-container">
    <div class="header">
        <div class="logo-wrapper">
            <img src="https://cdn-icons-png.flaticon.com/512/3144/3144456.png" alt="Grocery List Logo"/>
            <div class="title">
                Grocery List
            </div>
        </div>
    </div>
    <div class="content">
        <h2>Invitation to Grocery List</h2>
        <p>Hello,<br>
        You have been invited by <strong>{{ $user->listName }}</strong> to collaborate on the grocery list <strong>"{{ $list->name }}"</strong>.</p>
        <p>Create an account using the button below to access the list:</p>
        <a href="{{ $url }}" class="cta-button">Create Account</a>
        <p style="margin-top: 24px;">Already have an account? Log in with your existing credentials.</p>
        <p style="margin-top: 30px; text-align: center;">
            Enjoy organizing your grocery list together!<br>
            – The Grocery List Team
        </p>
    </div>
    <div class="footer">
        You are receiving this email because you have been invited to a grocery list on www.Tomjielis.com.<br>
    </div>
</div>
</body>
</html>

