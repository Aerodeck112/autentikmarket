<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5pt; line-height: 1.5; color: #1e2620; }
        h1 { font-size: 16pt; margin: 0 0 4pt; color: #2f4a3a; }
        h2 { font-size: 11.5pt; margin: 16pt 0 6pt; color: #2f4a3a; border-bottom: 0.6pt solid #e2d8c5; padding-bottom: 3pt; }
        p { margin: 0 0 7pt; }
        .muted { color: #6b6f69; font-size: 9pt; }
        .placeholder { background: #fbf3e2; border-left: 2pt solid #d9a43b; padding: 5pt 7pt; color: #6b4f12; font-style: italic; }
        table.data { width: 100%; border-collapse: collapse; margin: 10pt 0; }
        table.data th { text-align: left; width: 32%; background: #f6f1e7; padding: 5pt 7pt; border: 0.6pt solid #e2d8c5; font-weight: bold; }
        table.data td { padding: 5pt 7pt; border: 0.6pt solid #e2d8c5; }
        table.signatures { width: 100%; margin-top: 34pt; }
        table.signatures td { width: 50%; vertical-align: top; padding-right: 18pt; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 8pt; color: #8a8e88; text-align: center; }
    </style>
</head>
<body>
<div class="footer">Autentik Market · {{ $title }}</div>
{!! $body !!}
</body>
</html>
