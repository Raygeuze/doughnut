<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribed</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 24px;
            color: #1a2722;
        }
        nav {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        nav a {
            text-decoration: none;
            color: #0f7a5f;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <nav>
        <a href="{{ url('/') }}">Home</a>
        <a href="{{ route('admin.outreach.dashboard') }}">Dashboard</a>
        <a href="{{ route('admin.outreach.leads.index') }}">Leads</a>
        <a href="{{ route('admin.outreach.campaigns.index') }}">Campaigns</a>
    </nav>
    <h1>You are unsubscribed</h1>
    <p>Your address has been removed from future fraud-alert outreach.</p>
</body>
</html>
