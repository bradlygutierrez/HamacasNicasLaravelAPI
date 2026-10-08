@extends('pdf.layouts.document')
@php
    $money = fn ($value) => ($currency ?? 'C$') . ' ' . number_format((float) $value, 2);
    $productSubtotal = collect($details)->sum('subtotal');
@endphp
@section('content')
    <table class="proforma-brandbar">
        <tr>
            <td class="brand-logo">
                @if(!empty($company['logo']))
                    <img src="{{ $company['logo'] }}" style="max-width:180px; max-height:56px;">
                @else
                    <strong>{{ $company['nombre'] }}</strong>
                @endif
            </td>
            <td class="brand-contact">
                @if(!empty($company['web']))<span>{{ $company['web'] }}</span>@endif
                @if(!empty($company['facebook']))<span>{{ $company['facebook'] }}</span>@endif
                @if(!empty($company['correo']))<span>{{ $company['correo'] }}</span>@endif
                @if(!empty($company['instagram']) || !empty($company['youtube']))
                    <span>También estamos en: {{ $company['instagram'] ?? '' }} @if(!empty($company['youtube'])) · {{ $company['youtube'] }}@endif</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="proforma-title">
        <tr>
            <td>
                <h1>PROFORMA DE PEDIDO</h1>
                <div>{{ $dateFormatted ?? '—' }}</div>
            </td>
            <td class="proforma-number">
                @if($isDraft)<span class="draft-badge">BORRADOR</span>@endif
                <span>Proforma</span>
                <strong>{{ $number }}</strong>
            </td>
        </tr>
    </table>

    <table class="parties avoid-break">
        <tr>
            <td>
                <div class="party-label">Vendedor</div>
                <strong>{{ $company['nombre'] }}</strong>
                @if(!empty($company['ruc']))<div>RUC# {{ $company['ruc'] }}</div>@endif
                @if(!empty($company['direccion']))<div>{{ $company['direccion'] }}</div>@endif
                @if(!empty($company['ciudad']) || !empty($company['pais']))<div>{{ $company['ciudad'] ?? '' }}{{ !empty($company['ciudad']) && !empty($company['pais']) ? ', ' : '' }}{{ $company['pais'] ?? '' }}</div>@endif
            </td>
            <td>
                <div class="party-label">Cliente</div>
                <strong>{{ $proforma->nombre_cliente }}</strong>
                @if($proforma->ruc)<div>RUC# {{ $proforma->ruc }}</div>@endif
                @if($proforma->direccion)<div>{{ $proforma->direccion }}</div>@endif
                @if($proforma->telefono || $proforma->correo)<div>{{ $proforma->telefono }}@if($proforma->telefono && $proforma->correo) · @endif{{ $proforma->correo }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="commercial-table">
        <thead>
            <tr>
                <th class="qty">Cantidad</th>
                <th>Descripción</th>
                <th class="color">Color</th>
                <th class="money">Precio unit.</th>
                <th class="money">Descuento</th>
                <th class="money">Precio total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details as $detail)
                <tr class="product-row">
                    <td class="qty">{{ $detail['quantity'] }}</td>
                    <td>
                        <strong>{{ $detail['name'] }}</strong>
                        @if(!empty($detail['description']))<div class="item-description">{{ $detail['description'] }}</div>@endif
                    </td>
                    <td class="color">{{ !empty($detail['colors']) ? implode(' / ', $detail['colors']) : '—' }}</td>
                    <td class="money">{{ $money($detail['unit_price']) }}</td>
                    <td class="money">{{ $money($detail['discount']) }}</td>
                    <td class="money">{{ $money($detail['subtotal']) }}</td>
                </tr>
                @foreach($detail['services'] as $service)
                    <tr class="service-row">
                        <td class="qty">{{ $service['quantity'] }}</td>
                        <td><span class="service-mark">↳</span> {{ $service['name'] }}@if($service['detail'])<div class="item-description">{{ $service['detail'] }}</div>@endif</td>
                        <td class="color">—</td>
                        <td class="money">@if(!empty($service['has_breakdown']))<span class="approx">Aprox. </span>@endif{{ $money($service['unit_price']) }}</td>
                        <td class="money">{{ $money($service['discount']) }}</td>
                        <td class="money">{{ $money($service['subtotal']) }}</td>
                    </tr>
                @endforeach
            @endforeach
            @foreach($proforma->servicios as $service)
                <tr class="product-row">
                    <td class="qty">{{ $service->cantidad }}</td>
                    <td><strong>{{ $service->servicio_nombre_snapshot }}</strong>@if($service->detalle)<div class="item-description">{{ $service->detalle }}</div>@endif</td>
                    <td class="color">—</td>
                    <td class="money">@if($service->desglose->isNotEmpty())<span class="approx">Aprox. </span>@endif{{ $money($service->precio_unitario) }}</td>
                    <td class="money">{{ $money($service->descuento) }}</td>
                    <td class="money">{{ $money($service->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="goods-total"><span>Total productos</span><strong>{{ $money($productSubtotal) }}</strong></div>

    <table class="summary-layout">
        <tr>
            <td class="terms">
                @if($proforma->valida_hasta)<p>* Esta proforma es válida hasta el {{ $proforma->valida_hasta->format('d/m/Y') }}.</p>@endif
                @if($paymentConditions)<p>{{ $paymentConditions }}</p>@endif
                @if($proforma->observaciones)<p><strong>Observaciones:</strong> {{ $proforma->observaciones }}</p>@endif
                @if(!empty($company['telefono']) || !empty($company['correo']))
                    <p class="contact-prompt">Si tiene alguna duda, contáctenos:<br>{{ $company['correo'] ?? '' }} @if(!empty($company['correo']) && !empty($company['telefono'])) · @endif {{ $company['telefono'] ?? '' }}</p>
                @endif
            </td>
            <td>
                <table class="totals proforma-totals">
                    <tr><td>Subtotal bruto</td><td class="money">{{ $money($proforma->subtotal_bruto) }}</td></tr>
                    <tr><td>Descuentos por líneas</td><td class="money">- {{ $money($proforma->descuento_lineas ?? 0) }}</td></tr>
                    <tr><td>Descuento global</td><td class="money">- {{ $money($proforma->descuento_global ?? $proforma->descuento_total ?? $proforma->descuento ?? 0) }}</td></tr>
                    <tr class="net-total"><td>Base neta</td><td class="money">{{ $money($proforma->base_neta) }}</td></tr>
                    @if($proforma->aplica_iva)<tr><td>IVA</td><td class="money">{{ $money($proforma->monto_iva) }}</td></tr>@endif
                    @if($proforma->aplica_ir)<tr><td>IR</td><td class="money">- {{ $money(abs($proforma->monto_ir)) }}</td></tr>@endif
                    <tr class="grand-total"><td>TOTAL</td><td class="money">{{ $money($proforma->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="document-footer">{{ $company['web'] ?? '' }} @if(!empty($company['web']) && !empty($company['correo'])) · @endif {{ $company['correo'] ?? '' }} @if(!empty($company['telefono'])) · {{ $company['telefono'] }}@endif</div>

    @if(count($serviceBreakdowns))
        <div class="page-break">
            @foreach($serviceBreakdowns as $breakdown)
                <h2 class="proforma-section-title">DESGLOSE – {{ $breakdown['name'] }}</h2>
                <table class="breakdown-table avoid-break">
                    <thead><tr><th>Descripción</th><th class="money">Valor</th></tr></thead>
                    <tbody>
                        @foreach($breakdown['rows'] as $row)
                            <tr><td>{{ $row->descripcion }}</td><td class="money">{{ $money($row->monto) }}</td></tr>
                        @endforeach
                        <tr class="breakdown-total"><td>Total</td><td class="money">{{ $money($breakdown['rows']->sum('monto')) }}</td></tr>
                    </tbody>
                </table>
            @endforeach
        </div>
    @endif

    @foreach($sheets as $sheet)<div class="page-break">@include('pdf.proforma.product-sheet', ['sheet' => $sheet])</div>@endforeach
@endsection
