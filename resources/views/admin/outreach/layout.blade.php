<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Outreach Admin' }}</title>
    <style>
        :root {
            --bg: #f5f7f4;
            --panel: #ffffff;
            --ink: #1a2722;
            --muted: #5d6c65;
            --accent: #0f7a5f;
            --accent-soft: #d8f0e8;
            --warn: #a05b00;
            --danger: #9b1c1c;
            --line: #d5e0db;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at 0% 0%, #e9f7f1 0%, var(--bg) 35%);
            color: var(--ink);
        }
        header {
            border-bottom: 1px solid var(--line);
            background: var(--panel);
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
        }
        .links a {
            margin-left: 16px;
            color: var(--ink);
            text-decoration: none;
            font-weight: 600;
        }
        .links a:hover { color: var(--accent); }
        .brand {
            font-size: 20px;
            font-weight: 700;
            color: var(--accent);
            text-decoration: none;
        }
        main { padding: 24px 0 40px; }
        .flash {
            border: 1px solid #bce2d4;
            background: var(--accent-soft);
            color: #0d5f4b;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 2px 10px rgba(17, 28, 23, 0.04);
        }
        .grid {
            display: grid;
            gap: 16px;
        }
        .grid-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
        .grid-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .kpi {
            font-size: 26px;
            font-weight: 700;
            margin: 4px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            border-bottom: 1px solid var(--line);
            text-align: left;
            padding: 10px 8px;
            vertical-align: top;
        }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; }
        .tag {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid var(--line);
            background: #eef4f1;
        }
        .tag.eligible { background: #d8f0e8; color: #0d5f4b; }
        .tag.ineligible { background: #fde4e4; color: var(--danger); }
        .tag.unknown { background: #fff2dc; color: var(--warn); }
        .stack { display: flex; flex-direction: column; gap: 12px; }
        input, textarea, select {
            width: 100%;
            padding: 9px 10px;
            border-radius: 8px;
            border: 1px solid #b8cac2;
            background: #fff;
        }
        button {
            border: 0;
            border-radius: 8px;
            padding: 9px 12px;
            font-weight: 700;
            background: var(--accent);
            color: #fff;
            cursor: pointer;
        }
        button.secondary { background: #3f544a; }
        button.warn { background: #8d5509; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .muted { color: var(--muted); font-size: 13px; }
        .errors {
            margin: 0 0 12px;
            padding: 10px 14px;
            border: 1px solid #f3b3b3;
            background: #ffeaea;
            border-radius: 10px;
            color: #7a1919;
        }
        @media (max-width: 960px) {
            .grid-4, .grid-2 { grid-template-columns: 1fr; }
            .links a { margin-left: 10px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="shell nav">
            <a class="brand" href="{{ route('admin.outreach.dashboard') }}">Outreach Admin</a>
            <nav class="links">
                <a href="{{ url('/') }}">Home</a>
                <a href="{{ route('admin.outreach.dashboard') }}">Dashboard</a>
                <a href="{{ route('admin.outreach.leads.index') }}">Leads</a>
                <a href="{{ route('admin.outreach.campaigns.index') }}">Campaigns</a>
            </nav>
        </div>
    </header>

    <main class="shell">
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @yield('content')
    </main>
</body>
</html>
