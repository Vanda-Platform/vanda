<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $page['title'] ? $page['title'].' — ' : '' }}{{ $page['app']['name'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="{{ \Vanda\Vanda::asset('vanda.css') }}">
    <script type="module" src="{{ \Vanda\Vanda::asset('vanda.js') }}"></script>
</head>
<body>
    <div id="vanda-app"></div>
    <script type="application/json" id="vanda-page">@json($page, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)</script>
</body>
</html>
