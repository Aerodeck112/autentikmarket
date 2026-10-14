<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f1e7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1e2620">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f1e7;padding:32px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #e2d8c5">
            <tr><td style="background:#2f4a3a;padding:22px 28px">
                <span style="font-family:Georgia,'Times New Roman',serif;font-size:22px;color:#f6f1e7;font-weight:bold">Autentik</span>
                <span style="font-size:11px;letter-spacing:4px;color:#d9a43b;font-weight:bold;text-transform:uppercase;margin-left:6px">Market</span>
            </td></tr>
            <tr><td style="padding:30px 28px 10px;font-size:15px;line-height:1.6;color:#2b322c">
                <h1 style="font-family:Georgia,'Times New Roman',serif;font-size:22px;line-height:1.3;margin:0 0 18px;color:#1e2620">{{ $subject }}</h1>
                {!! $bodyHtml !!}
            </td></tr>
            @if ($actionUrl && $actionLabel)
                <tr><td style="padding:6px 28px 26px">
                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#b4532a;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 22px;border-radius:999px">{{ $actionLabel }}</a>
                </td></tr>
            @endif
            <tr><td style="padding:18px 28px 26px;border-top:1px solid #efe7d8;font-size:12px;line-height:1.5;color:#7a7f78">
                {{ __('Autentik Market — târg lunar de producători și artizani.') }}<br>
                <a href="{{ config('app.url') }}" style="color:#7a7f78">{{ preg_replace('#^https?://#', '', config('app.url')) }}</a>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
