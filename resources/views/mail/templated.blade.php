<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#faf3e1;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1a2b20">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf3e1;padding:32px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e9ddbf">
            <tr><td style="background:#1d5733;padding:22px 28px">
                <img src="{{ asset('images/brand/logo-wordmark-light.png') }}" width="150" height="52" alt="Autentik Market" style="display:block;border:0;height:52px;width:150px">
            </td></tr>
            <tr><td style="padding:30px 28px 10px;font-size:15px;line-height:1.6;color:#26372b">
                <h1 style="font-family:'Oswald','Arial Narrow',Arial,sans-serif;font-size:24px;font-weight:600;line-height:1.25;margin:0 0 18px;color:#1a2b20">{{ $subject }}</h1>
                {!! $bodyHtml !!}
            </td></tr>
            @if ($actionUrl && $actionLabel)
                <tr><td style="padding:6px 28px 26px">
                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#dd8d2b;color:#133e23;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 22px;border-radius:999px">{{ $actionLabel }}</a>
                </td></tr>
            @endif
            <tr><td style="padding:18px 28px 26px;border-top:1px solid #f0e6cc;font-size:12px;line-height:1.5;color:#5b6a5f">
                {{ __('Autentik Market — târg lunar de producători și artizani.') }}<br>
                <a href="{{ config('app.url') }}" style="color:#5b6a5f">{{ preg_replace('#^https?://#', '', config('app.url')) }}</a>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
