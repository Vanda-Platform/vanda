@php
    $known = in_array($status, [401, 403, 404, 419, 429, 500, 503], true) ? $status : 500;
    $title = __("vanda::errors.$known.title");
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('vanda.name', 'Vanda') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
    @foreach (\Vanda\Vanda::entry()['styles'] as $style)
    <link rel="stylesheet" href="{{ $style }}">
    @endforeach
    <script>
        try {
            const theme = localStorage.getItem('vanda:theme') ?? 'system';
            const dark = theme === 'dark' || (theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        } catch {}
    </script>
    <style>
        .vanda-error { min-height: 100dvh; display: grid; place-items: center; padding: 24px 16px; background: var(--v-bg); color: var(--v-fg); font-family: var(--font-sans); }
        .vanda-error__card { width: 100%; max-width: 440px; display: flex; flex-direction: column; gap: 16px; padding: 32px; border: 1px solid var(--v-line); border-radius: 16px; background: var(--v-surface); box-shadow: 0 1px 2px rgb(24 24 27 / 0.04); }
        .vanda-error__code { font-family: var(--font-mono); font-size: 12.5px; color: var(--v-fg-3); }
        .vanda-error h1 { margin: 0; font-size: 22px; line-height: 1.3; font-weight: 600; letter-spacing: -0.01em; text-wrap: balance; }
        .vanda-error p { margin: 0; font-size: 14.5px; line-height: 1.6; color: var(--v-fg-2); }
        .vanda-error__actions { display: flex; flex-wrap: wrap; gap: 8px; padding-top: 4px; }
        .vanda-error a { display: inline-flex; align-items: center; height: 36px; padding: 0 12px; border-radius: 8px; font-size: 13.5px; font-weight: 500; text-decoration: none; }
        .vanda-error__primary { background: var(--v-accent); color: #fff; }
        .vanda-error__secondary { border: 1px solid var(--v-line-strong); color: var(--v-fg); background: var(--v-surface); }
        .vanda-error__mark { width: 40px; height: 40px; border-radius: 999px; display: grid; place-items: center; background: var(--v-accent-soft); color: var(--v-accent-fg); font-weight: 600; }
    </style>
</head>
<body>
    <main class="vanda-error">
        <div class="vanda-error__card">
            <span class="vanda-error__mark" aria-hidden="true">{{ mb_substr(config('vanda.name', 'Vanda'), 0, 1) }}</span>
            <span class="vanda-error__code">{{ __('vanda::errors.code', ['code' => $status]) }}</span>
            <h1>{{ $title }}</h1>
            <p>{{ __("vanda::errors.$known.text") }}</p>
            <div class="vanda-error__actions">
                <a class="vanda-error__primary" href="{{ $home }}">{{ __('vanda::errors.home') }}</a>
                <a class="vanda-error__secondary" href="{{ url()->previous() }}">{{ __('vanda::errors.back') }}</a>
            </div>
        </div>
    </main>
</body>
</html>
