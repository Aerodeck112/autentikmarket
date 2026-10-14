<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ trim(strip_tags($__env->yieldContent('code'))) }} · Autentik Market</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #faf3e1; color: #1a2b20; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .box { max-width: 520px; padding: 40px 24px; text-align: center; }
        .code { font-family: 'Oswald', 'Arial Narrow', Impact, sans-serif; font-weight: 600; font-size: 96px; line-height: 1; color: #1d5733; margin: 0; }
        .code span { color: #dd8d2b; }
        h1 { font-family: 'Oswald', 'Arial Narrow', sans-serif; font-weight: 600; font-size: 26px; margin: 18px 0 10px; }
        p { color: #4b5a4f; line-height: 1.6; }
        a { display: inline-block; margin-top: 18px; background: #1d5733; color: #faf3e1; text-decoration: none; padding: 12px 22px; border-radius: 999px; font-weight: 600; font-size: 14px; }
        .brand img { height: 64px; width: auto; margin-bottom: 28px; }
    </style>
</head>
<body>
<div class="box">
    <div class="brand"><img src="{{ asset('images/brand/logo-wordmark.png') }}" alt="Autentik Market" width="186" height="64"></div>
    <p class="code">@yield('code')</p>
    <h1>@yield('title')</h1>
    <p>@yield('message')</p>
    @hasSection('nolink')
    @else
        <a href="{{ url('/') }}">{{ app()->getLocale() === 'hu' ? 'Vissza a főoldalra' : 'Înapoi la pagina principală' }}</a>
    @endif
</div>
</body>
</html>
