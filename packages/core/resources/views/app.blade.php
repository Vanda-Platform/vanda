<!doctype html>
{{-- The panel's density before the first paint; the browser applies the user's own choice over it. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-density="{{ \Vanda\Navigation\Density::configured()->value }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($page['props']['page']['title'] ?? null) ? $page['props']['page']['title'].' — ' : '' }}{{ $page['props']['page']['app']['name'] ?? config('vanda.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ \Vanda\Panel::favicon() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" data-vanda-fonts>
    @foreach (\Vanda\Panel::entry()['styles'] as $style)
    <link rel="stylesheet" href="{{ $style }}">
    @endforeach
    <script type="module" src="{{ \Vanda\Panel::entry()['script'] }}"></script>
</head>
<body>
    @inertia
</body>
</html>
