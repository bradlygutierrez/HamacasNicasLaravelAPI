@extends('pdf.layouts.document')
@section('content')
    <table class="brandbar">
        <tr>
            <td class="brand">
                @if(!empty($company['logo']))
                    <img src="{{ $company['logo'] }}" alt="{{ $company['nombre'] }}">
                @else
                    <strong>{{ $company['nombre'] }}</strong>
                @endif
            </td>
            <td class="contact">
                <strong>{{ $company['nombre'] }}</strong><br>
                @if(!empty($company['telefono'])){{ $company['telefono'] }}<br>@endif
                @if(!empty($company['correo'])){{ $company['correo'] }}@endif
            </td>
        </tr>
    </table>

    <header class="document-heading">
        <div>
            <p class="eyebrow">Preparación de pedido</p>
            <h1>Lista de compra de materiales</h1>
            <p class="subheading">Revisá las cantidades planificadas antes de iniciar la fabricación.</p>
        </div>
        <div class="order-ref">
            <span>Pedido</span>
            <strong>{{ $pedido->numero }}</strong>
            <small>Generado {{ $generatedAt }}</small>
        </div>
    </header>

    <table class="order-info">
        <tr>
            <td><span>Cliente</span><strong>{{ $pedido->nombre_cliente }}</strong></td>
            <td><span>Proforma</span><strong>{{ $pedido->proforma_numero_snapshot ?: '—' }}</strong></td>
            <td><span>Fecha del pedido</span><strong>{{ $pedido->fecha_pedido?->format('d/m/Y') ?: '—' }}</strong></td>
        </tr>
    </table>

    <h2>Materiales necesarios</h2>
    @if($materials->isEmpty())
        <p class="empty">Este pedido no requiere compras adicionales de materiales.</p>
    @else
        <table class="materials-table">
            <thead>
                <tr><th>Material</th><th class="quantity">Necesario</th><th class="quantity">Comprar</th><th class="check">Comprado</th></tr>
            </thead>
            <tbody>
                @foreach($materials as $material)
                    <tr>
                        <td><strong>{{ $material->material_nombre_snapshot }}</strong></td>
                        <td class="quantity">{{ number_format((float) $material->cantidad_requerida, 4) }} {{ $material->unidad_consumo_snapshot }}</td>
                        <td class="quantity buy-quantity">{{ number_format((float) $material->cantidad_compra_plan, 2) }} {{ $material->unidad_compra_snapshot }}</td>
                        <td class="check"><span></span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="helper">Marcá la última columna al completar cada compra. Las cantidades de compra consideran los empaques registrados en el plan del pedido.</p>
    @endif

    <footer>Hamacas Nica · {{ $pedido->numero }} · Lista interna de compra</footer>

    <style>
        .brandbar { border-top: 5px solid #002060; border-bottom: 1px solid #d7e0e7; padding: 9px 0 11px; }
        .brandbar td { vertical-align: middle; }
        .brand { width: 58%; color: #002060; font-size: 20px; }
        .brand img { max-width: 210px; max-height: 60px; }
        .contact { text-align: right; color: #526574; font-size: 9px; line-height: 1.6; }
        .document-heading { display: table; width: 100%; margin: 25px 0 18px; border-bottom: 2px solid #e3b72e; padding-bottom: 13px; }
        .document-heading > div { display: table-cell; vertical-align: bottom; }
        .eyebrow { color: #63717a; font-size: 9px; font-weight: bold; letter-spacing: .7px; text-transform: uppercase; }
        h1 { margin-top: 4px; color: #002060; font-size: 20px; }
        .subheading { margin-top: 5px; color: #526574; font-size: 9px; }
        .order-ref { width: 24%; text-align: right; }
        .order-ref span, .order-ref strong, .order-ref small { display: block; }
        .order-ref span { color: #63717a; font-size: 8px; text-transform: uppercase; }
        .order-ref strong { margin: 3px 0; color: #002060; font-size: 14px; }
        .order-ref small { color: #63717a; font-size: 8px; }
        .order-info { margin-bottom: 25px; }
        .order-info td { width: 33.3%; padding: 9px 11px; border-left: 3px solid #e3b72e; background: #f5f7f9; vertical-align: top; }
        .order-info td + td { border-left-color: #002060; }
        .order-info span, .order-info strong { display: block; }
        .order-info span { color: #63717a; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .order-info strong { margin-top: 4px; color: #202b33; font-size: 10px; }
        h2 { margin: 0 0 8px; padding-left: 8px; border-left: 3px solid #e3b72e; color: #002060; font-size: 12px; }
        .materials-table th { padding: 9px 8px; background: #002060; color: white; font-size: 9px; text-align: left; }
        .materials-table td { padding: 11px 8px; border-bottom: 1px solid #dbe2e7; vertical-align: middle; }
        .materials-table tbody tr:nth-child(even) { background: #f8fafb; }
        .materials-table .quantity { width: 22%; text-align: right; white-space: nowrap; }
        .materials-table .buy-quantity { color: #002060; font-weight: bold; }
        .materials-table .check { width: 12%; text-align: center; }
        .check span { display: inline-block; width: 15px; height: 15px; border: 1px solid #82919b; }
        .helper { margin-top: 10px; color: #63717a; font-size: 8px; }
        .empty { padding: 14px; background: #f5f7f9; color: #526574; }
        footer { position: fixed; right: 0; bottom: -18px; left: 0; border-top: 1px solid #d7e0e7; padding-top: 7px; color: #63717a; font-size: 8px; text-align: center; }
    </style>
@endsection
