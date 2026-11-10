<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ __('public.unavailable_title') }}</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font:16px/1.5 system-ui,sans-serif;color:#1c1917;background:#faf8f6;text-align:center;padding:1rem}h1{font-size:1.3rem}</style>
</head>
<body><main><h1>{{ __('public.unavailable_title') }}</h1><p>{{ __('public.unavailable_body', ['name' => $tenant->name]) }}</p></main></body>
</html>
