<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $alreadyUnsubscribed ? 'Already unsubscribed' : 'Unsubscribed' }} | {{ $appName }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #eef6f7;
            --card: rgba(255, 255, 255, 0.96);
            --line: rgba(15, 23, 42, 0.08);
            --text: #0f172a;
            --muted: #52627a;
            --accent: #102b4c;
            --soft: #dbeafe;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(16, 43, 76, 0.08), transparent 42%),
                linear-gradient(180deg, #f8fbfc 0%, var(--bg) 100%);
            color: var(--text);
        }
        .panel {
            width: min(100%, 640px);
            padding: 32px;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: var(--card);
            box-shadow: 0 24px 64px rgba(15, 23, 42, 0.08);
        }
        .eyebrow {
            margin: 0 0 12px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: #0f766e;
        }
        h1 {
            margin: 0 0 12px;
            font-size: clamp(1.9rem, 3vw, 2.5rem);
            line-height: 1.08;
        }
        p {
            margin: 0 0 16px;
            font-size: 1rem;
            line-height: 1.7;
            color: var(--muted);
        }
        .meta {
            margin: 24px 0 0;
            padding: 14px 16px;
            border-radius: 14px;
            background: var(--soft);
            color: var(--accent);
            font-size: 0.95rem;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 8px;
            min-height: 46px;
            padding: 0 18px;
            border-radius: 999px;
            background: var(--accent);
            color: #fff;
            font-weight: 600;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="panel">
        <p class="eyebrow">Email Preferences</p>
        <h1>{{ $alreadyUnsubscribed ? 'This address is already removed.' : 'You have been unsubscribed.' }}</h1>
        <p>
            {{ $alreadyUnsubscribed
                ? 'This email address was already removed from future supplier outreach sends.'
                : 'This email address will no longer receive supplier outreach emails from '.$appName.'.' }}
        </p>
        <div class="meta">
            Recipient: <strong>{{ $contact->email }}</strong>
        </div>
        <a class="button" href="{{ $homeUrl }}">Return to {{ $appName }}</a>
    </main>
</body>
</html>
