<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #fff; color: #0f172a; }
        @media (prefers-color-scheme: dark) { body { background: #020817; color: #f1f5f9; } }
        h1 { font-size: 1.5rem; margin: 0; }
    </style>
</head>
<body>
    <main><h1>{{ config('app.name') }}</h1></main>
</body>
</html>
