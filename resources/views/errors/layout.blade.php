<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ trim(strip_tags($__env->yieldContent('code'))) }} · Autentik Market</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f6f1e7; color: #1e2620; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .box { max-width: 520px; padding: 40px 24px; text-align: center; }
        .code { font-family: Georgia, serif; font-size: 92px; line-height: 1; color: #2f4a3a; margin: 0; letter-spacing: -2px; }
        .code span { color: #b4532a; }
        h1 { font-family: Georgia, serif; font-size: 26px; margin: 18px 0 10px; }
        p { color: #4a5249; line-height: 1.6; }
        a { display: inline-block; margin-top: 18px; background: #2f4a3a; color: #f6f1e7; text-decoration: none; padding: 12px 22px; border-radius: 999px; font-weight: 600; font-size: 14px; }
        .brand { font-family: Georgia, serif; font-weight: bold; font-size: 18px; margin-bottom: 30px; }
        .brand small { display: block; font-family: sans-serif; font-size: 10px; letter-spacing: 4px; color: #b4532a; }
    </style>
</head>
<body>
<div class="box">
    <div class="brand">Autentik<small>MARKET</small></div>
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
