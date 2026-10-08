<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Documento comercial' }}</title>
    <style>
        @page { margin: 30px 38px 40px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #202b33; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
        h1, h2, h3, p { margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        .blue { color: #002060; }
        .muted { color: #63717a; }
        .small { font-size: 8px; }
        .top-rule { border-top: 3px solid #002060; }
        .section-title { margin: 20px 0 7px; color: #002060; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; }
        .page-break { page-break-before: always; }
        .avoid-break { page-break-inside: avoid; }
        .amount { text-align: right; white-space: nowrap; }
        .totals { width: 44%; margin-left: auto; margin-top: 12px; }
        .totals td { padding: 3px 0; }
        .total-row td { border-top: 2px solid #002060; color: #002060; font-size: 14px; font-weight: bold; padding-top: 8px; }
        .footer { margin-top: 28px; border-top: 1px solid #ccd4d9; padding-top: 8px; color: #63717a; font-size: 8px; }
        .pdf-table thead { display: table-header-group; }
        .pdf-table tr.item-row { page-break-inside: avoid; }
        .sheet-header { border-top: 3px solid #002060; padding-top: 9px; margin-bottom: 24px; }
        .sheet-photo-main { width: 100%; max-width: 390px; max-height: 390px; }
        .sheet-photo-small { width: 170px; max-height: 145px; }
        .proforma-brandbar { border-top: 5px solid #002060; border-bottom: 1px solid #d7e0e7; padding: 8px 0 9px; }
        .proforma-brandbar td { vertical-align: middle; }
        .brand-logo { width: 42%; color: #002060; font-size: 21px; }
        .brand-contact { text-align: right; color: #526574; font-size: 8px; line-height: 1.6; }
        .brand-contact span { display: block; }
        .proforma-title { margin: 12px 0 10px; border-bottom: 2px solid #e3b72e; }
        .proforma-title h1 { color: #002060; font-size: 18px; letter-spacing: .5px; }
        .proforma-title td { padding: 3px 0 9px; vertical-align: bottom; }
        .proforma-title td:first-child { text-align: center; }
        .proforma-title td:first-child div { margin-top: 3px; color: #526574; font-size: 9px; }
        .proforma-number { width: 19%; text-align: right; color: #526574; font-size: 8px; }
        .proforma-number span, .proforma-number strong { display: block; }
        .proforma-number strong { margin-top: 2px; color: #002060; font-size: 11px; }
        .draft-badge { display: inline-block !important; margin: 0 0 4px; padding: 2px 5px; border: 1px solid #9aa8b1; color: #63717a; }
        .parties { margin: 8px 0 14px; }
        .parties td { width: 50%; padding: 8px 11px; border-left: 3px solid #e3b72e; background: #f5f7f9; vertical-align: top; line-height: 1.55; }
        .parties td + td { border-left-color: #002060; }
        .parties strong { color: #002060; }
        .party-label { margin-bottom: 3px; color: #63717a; font-size: 8px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; }
        .commercial-table { margin-top: 2px; table-layout: fixed; }
        .commercial-table thead { display: table-header-group; }
        .commercial-table th { padding: 7px 6px; background: #002060; color: #fff; font-size: 8px; text-align: left; }
        .commercial-table th.money, .commercial-table th.qty { text-align: right; }
        .commercial-table th.color { text-align: left; }
        .commercial-table td { padding: 7px 6px; border-bottom: 1px solid #dbe2e7; vertical-align: top; }
        .commercial-table .qty { width: 8%; text-align: right; white-space: nowrap; }
        .commercial-table .color { width: 20%; color: #526574; font-size: 8px; }
        .commercial-table th:nth-child(2) { width: 34%; }
        .commercial-table .money { width: 13%; text-align: right; white-space: nowrap; }
        .commercial-table tr { page-break-inside: avoid; }
        .commercial-table .product-row:nth-child(even) { background: #f8fafb; }
        .commercial-table .service-row { color: #526574; background: #fbfcfd; font-size: 8px; }
        .service-mark { color: #e0ad16; font-weight: bold; }
        .item-description { margin-top: 2px; color: #63717a; font-size: 8px; }
        .approx { color: #63717a; font-size: 7px; }
        .goods-total { margin: 7px 0 10px; padding: 6px 9px; background: #eef2f5; color: #002060; text-align: right; font-size: 9px; }
        .goods-total strong { margin-left: 28px; }
        .proforma-section-title { margin: 13px 0 5px; padding-left: 7px; border-left: 3px solid #e3b72e; color: #002060; font-size: 10px; font-weight: bold; letter-spacing: .4px; }
        .breakdown-table { width: 58%; margin-left: auto; }
        .breakdown-table th { padding: 5px 7px; background: #eef2f5; color: #002060; text-align: left; }
        .breakdown-table td { padding: 4px 7px; border-bottom: 1px solid #e1e6ea; }
        .breakdown-table .money { text-align: right; white-space: nowrap; }
        .breakdown-total td { border-top: 2px solid #002060; color: #002060; font-weight: bold; }
        .summary-layout { margin-top: 14px; }
        .summary-layout > tbody > tr > td { width: 50%; vertical-align: top; }
        .terms { padding: 3px 20px 0 0; color: #526574; font-size: 8px; }
        .terms p { margin-bottom: 5px; }
        .contact-prompt { margin-top: 10px !important; color: #002060; }
        .proforma-totals { width: 100%; margin: 0 0 0 auto; padding: 7px 10px; background: #f5f7f9; }
        .proforma-totals td { padding: 3px 0; }
        .proforma-totals .net-total td { border-top: 1px solid #b8c4ce; font-weight: bold; }
        .proforma-totals .grand-total td { border-top: 2px solid #e3b72e; color: #002060; font-size: 14px; font-weight: bold; padding-top: 6px; }
        .document-footer { margin-top: 12px; padding-top: 6px; border-top: 1px solid #d7e0e7; color: #63717a; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
