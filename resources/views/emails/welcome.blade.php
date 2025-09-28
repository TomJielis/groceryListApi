<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Boodschappenlijst</title>
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

    <!-- Header met perfect gecentreerd logo en titel -->
    <div class="header">
        <div class="logo-wrapper">
            <img src="https://cdn-icons-png.flaticon.com/512/3144/3144456.png" alt="Boodschappenlijst Logo"/>
            <div class="title">
                Boodschappenlijst
            </div>
        </div>
    </div>

    <div class="content">
        <br>
        <p style="text-align: center">Hallo {{$user->name}}👋,</p>
        <p style="text-align: center">
            Welkom bij Boodschappenlijst!
            Met je nieuwe account kun je snel en eenvoudig je boodschappenlijsten beheren, waar je ook bent.
            Deel lijsten eenvoudig met vrienden of familie, werk samen in real-time en vergeet nooit meer een item.
            Begin vandaag nog met organiseren en geniet van een slimmere, handigere manier van winkelen!
        </p>

        <a href="{{$url}}" class="cta-button">Activeer je account</a>
        <br>

        <div class="tips">
            <h3>💡 Tips voor gebruik</h3>
            <ul>
                <li>✅ Stel je hoofdlijst in als favoriet op de lijstenpagina, zodat je er direct toegang toe hebt vanaf het dashboard.</li>
                <li>👥 Deel je lijst met vrienden en werk samen binnen de app. Dit kun je instellen vanaf je lijstenpagina.</li>
                <li>📱 Gebruik het op mobiel en desktop.</li>
            </ul>
        </div>
        <div>
            <h2 style="text-align: center; margin-top: 40px;">
                Voeg Boodschappenlijst toe aan je Startscherm
            </h2>

            <h4 style="text-align: center; margin-top: 40px;">
                Instructies voor iPhone (Safari):
            </h4>
            <ul style="text-align: center; padding-left: 0;">
                <li>1. Open de app in Safari.</li>
                <li>2. Tik op het Deel-icoon (vierkant met pijl, onderaan).</li>
                <li>3. Scroll naar beneden en tik op "Zet op beginscherm".</li>
                <li>4. Bevestig de naam en tik op "Voeg toe".</li>
            </ul>

            <h4 style="text-align: center; margin-top: 40px;">
                Instructies voor Android (Chrome of andere browsers):
            </h4>

            <ul style="text-align: center; padding-left: 0;">
                <li>Open de app in je browser.</li>
                <li>Tik op de drie puntjes (⋮) in de rechterbovenhoek.</li>
                <li>Selecteer "Toevoegen aan startscherm".</li>
                <li>Bevestig de naam en tik op "Toevoegen".</li>
            </ul>

            <p style="text-align: center; margin-top: 20px;">
                Je vindt nu Boodschappenlijst op je startscherm — met een eigen icoon!
            </p>
        </div>
        <p style="margin-top: 30px; text-align: center;">
            We wensen je een soepele en plezierige winkelervaring<br>
            – Het Boodschappenlijst Team
        </p>
    </div>

    <div class="footer">
        Je ontvangt deze e-mail omdat je een account hebt aangemaakt op www.Tomjielis.com. <br>
    </div>
</div>
</body>
</html>