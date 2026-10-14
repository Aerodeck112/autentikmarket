<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; line-height: 1.5; color: #1a2b20; }
        h1 { font-size: 16pt; margin: 0 0 4pt; color: #1d5733; }
        h2 { font-size: 11.5pt; margin: 16pt 0 6pt; color: #1d5733; border-bottom: 0.6pt solid #e9ddbf; padding-bottom: 3pt; }
        p { margin: 0 0 7pt; }
        .muted { color: #6b6f69; font-size: 9pt; }
        .placeholder { background: #fbf1d6; border-left: 2pt solid #dd8d2b; padding: 5pt 7pt; color: #6b430a; font-style: italic; }
        table.data { width: 100%; border-collapse: collapse; margin: 10pt 0; }
        table.data th { text-align: left; width: 32%; background: #faf3e1; padding: 5pt 7pt; border: 0.6pt solid #e9ddbf; font-weight: bold; }
        table.data td { padding: 5pt 7pt; border: 0.6pt solid #e9ddbf; }
        table.signatures { width: 100%; margin-top: 34pt; }
        table.signatures td { width: 50%; vertical-align: top; padding-right: 18pt; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8pt; color: #8a8e88; text-align: center; }
        .brand { margin: 0 0 12pt; padding-bottom: 8pt; border-bottom: 1.2pt solid #dd8d2b; }
    </style>
</head>
<body>
<div class="brand"><img src="{{ public_path('images/brand/logo-wordmark.png') }}" alt="Autentik Market" style="height:30pt;width:auto"></div>
<div class="footer">Autentik Market · {{ $title }}</div>
{!! $body !!}
</body>
</html>
