<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Désabonnement confirmé — Termini Im</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Instrument Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #F7F2EA;
            color: #171311;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .card {
            background-color: #FCF7EE;
            border: 1px solid #D9CAB3;
            border-radius: 16px;
            max-width: 480px;
            width: 100%;
            padding: 48px 40px;
            text-align: center;
        }

        .logo-dot {
            width: 12px;
            height: 12px;
            background-color: #7A2232;
            border-radius: 50%;
            margin: 0 auto 12px;
        }

        .logo-wordmark {
            font-size: 22px;
            font-weight: 600;
            color: #171311;
            letter-spacing: -0.01em;
            margin-bottom: 32px;
        }

        .logo-wordmark span {
            color: #7A2232;
            font-style: italic;
        }

        .icon-check {
            width: 56px;
            height: 56px;
            background-color: #7A2232;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon-check svg {
            width: 28px;
            height: 28px;
            stroke: #FCF7EE;
            fill: none;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        h1 {
            font-family: 'Fraunces', Georgia, serif;
            font-size: 24px;
            font-weight: 600;
            color: #171311;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .subtitle {
            font-size: 15px;
            color: #332C29;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .email-hint {
            font-size: 13px;
            color: #716059;
            margin-top: 16px;
        }

        .divider {
            border: none;
            border-top: 1px solid #E8DCC8;
            margin: 32px 0;
        }

        .footer-note {
            font-size: 12px;
            color: #716059;
            line-height: 1.5;
        }

        .tagline {
            font-size: 11px;
            letter-spacing: 0.28em;
            color: #716059;
            text-transform: uppercase;
            margin-top: 32px;
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="card">

        <div class="logo-dot"></div>
        <div class="logo-wordmark">Termini <span>im</span></div>

        <div class="icon-check">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>

        <h1>Désabonnement confirmé</h1>

        <p class="subtitle">
            Tu ne recevras plus ce type de notification de notre part.
        </p>

        @if($email)
            <p class="email-hint">{{ $email }}</p>
        @endif

        <hr class="divider">

        <p class="footer-note">
            Tu peux gérer toutes tes préférences de notifications directement
            dans l'application Termini Im, section <strong>Réglages → Notifications</strong>.
        </p>

        <p class="tagline">PRISHTINË · 2026</p>

    </div>
</body>
</html>
