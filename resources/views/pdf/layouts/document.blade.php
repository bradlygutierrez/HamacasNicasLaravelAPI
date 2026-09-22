<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Documento comercial' }}</title>
    <style>
        @page { margin: 35px 42px 48px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #202b33; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        h1, h2, h3, p { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .blue { color: #123852; }
        .muted { color: #63717a; }
        .small { font-size: 8px; }
        .top-rule { border-top: 3px solid #123852; }
        .section-title { margin: 20px 0 7px; color: #123852; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; }
        .page-break { page-break-before: always; }
        .avoid-break { page-break-inside: avoid; }
        .amount { text-align: right; white-space: nowrap; }
        .totals { width: 44%; margin-left: auto; margin-top: 12px; }
        .totals td { padding: 3px 0; }
        .total-row td { border-top: 2px solid #123852; color: #123852; font-size: 14px; font-weight: bold; padding-top: 8px; }
        .footer { margin-top: 28px; border-top: 1px solid #ccd4d9; padding-top: 8px; color: #63717a; font-size: 8px; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
