@extends('pdf.layouts.document')
@php($money = fn ($value) => ($currency ?? 'C$') . ' ' . number_format((float) $value, 2))
@section('styles')
    <style>
        @page { margin: 35px 48px 45px; }
        .invoice-header { margin-bottom: 17px; }
        .invoice-header td { vertical-align: middle; }
        .invoice-logo { width: 25%; }
        .invoice-logo img { max-width: 205px; max-height: 78px; }
        .invoice-brand { width: 39%; color: #355f9c; font-size: 15px; }
        .invoice-brand-title { margin-bottom: 3px; font-size: 17px; }
        .invoice-brand-contact { font-size: 10px; line-height: 1.25; }
        .invoice-brand-contact div { white-space: nowrap; }
        .invoice-date { width: 36%; text-align: right; font-size: 11px; }
        .invoice-date strong { color: #202b33; }
        .invoice-parties { margin: 8px 0 24px; }
        .invoice-parties td { width: 50%; padding-right: 20px; vertical-align: top; }
        .invoice-party-label { margin-bottom: 2px; color: #587fb3; font-size: 14px; }
        .invoice-party-details { color: #767676; font-size: 12px; line-height: 1.35; }
        .invoice-party-details strong { color: #666; }
        .invoice-table { table-layout: fixed; }
        .invoice-table th, .invoice-table td { border: 1px solid #999; }
        .invoice-table th { padding: 6px; color: #333; font-size: 12px; text-align: center; }
        .invoice-table td { padding: 6px 8px; vertical-align: top; }
        .invoice-qty { width: 9%; }
        .invoice-description { width: 76%; }
        .invoice-total { width: 15%; text-align: right; white-space: nowrap; }
        .invoice-item-description { color: #666; font-size: 11px; line-height: 1.25; }
        .invoice-item-description + .invoice-item-description { margin-top: 2px; }
        .invoice-service td { color: #666; }
        .invoice-summary td { padding-top: 4px; padding-bottom: 4px; }
        .invoice-grand-total td { border-top: 2px solid #777; font-weight: bold; }
        .invoice-footer { margin-top: 28px; }
        .invoice-footer td { width: 50%; vertical-align: bottom; }
        .invoice-notes { color: #444; font-size: 11px; line-height: 1.45; }
        .invoice-signature { text-align: right; }
        .invoice-signature img { max-width: 140px; max-height: 70px; margin-left: 12px; vertical-align: middle; }
        .invoice-bottom-rule { margin-top: 22px; border-top: 1px dashed #aaa; }
    </style>
@endsection
@section('content')
    <table class="invoice-header">
        <tr>
            <td class="invoice-logo">
                @if(!empty($company['logo']))<img src="{{ $company['logo'] }}">@else<strong>{{ $company['nombre'] }}</strong>@endif
            </td>
            <td class="invoice-brand">
                <div class="invoice-brand-title">{{ $labels['invoice_title'] }}</div>
                <div class="invoice-brand-contact">
                    @if(!empty($company['web']))<div>{{ $company['web'] }}</div>@endif
                    @if(!empty($company['facebook']))<div>{{ $company['facebook'] }}</div>@endif
                    @if(!empty($company['correo']))<div>{{ $company['correo'] }}</div>@endif
                </div>
            </td>
            <td class="invoice-date">
                <div>{{ $labels['date'] }}: <strong>{{ $dateFormatted ?? '—' }}</strong></div>
                <div>{{ $labels['invoice_number'] }}: <strong>{{ $factura->numero }}</strong></div>
            </td>
        </tr>
    </table>

    <table class="invoice-parties">
        <tr>
            <td>
                <div class="invoice-party-label">{{ $labels['sender'] }}</div>
                <div class="invoice-party-details">
                    <strong>{{ $company['nombre'] }}</strong>
                    @if(!empty($company['ruc']))<div>{{ $company['ruc'] }}</div>@endif
                    @if(!empty($company['direccion']))<div>{{ $company['direccion'] }}</div>@endif
                    @if(!empty($company['ciudad']) || !empty($company['pais']))<div>{{ $company['ciudad'] ?? '' }}{{ !empty($company['ciudad']) && !empty($company['pais']) ? ', ' : '' }}{{ $company['pais'] ?? '' }}</div>@endif
                    @if(!empty($company['telefono']))<div>{{ $company['telefono'] }}</div>@endif
                </div>
            </td>
            <td>
                <div class="invoice-party-label">{{ $labels['receiver'] }}</div>
                <div class="invoice-party-details">
                    <strong>{{ $factura->nombre_cliente }}</strong>
                    @if($factura->ruc)<div>{{ $factura->ruc }}</div>@endif
                    @if($factura->direccion)<div>{{ $factura->direccion }}</div>@endif
                    @if($factura->telefono)<div>{{ $factura->telefono }}</div>@endif
                    @if($factura->correo)<div>{{ $factura->correo }}</div>@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="invoice-table">
        <thead>
            <tr>
                <th class="invoice-qty">{{ $labels['quantity'] }}</th>
                <th class="invoice-description">{{ $labels['description'] }}</th>
                <th class="invoice-total">{{ $labels['total'] }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details as $detail)
                <tr>
                    <td class="invoice-qty">{{ $detail['quantity'] }}</td>
                    <td>
                        <strong>{{ $detail['name'] }}</strong>
                        @if(!empty($detail['description']))<div class="invoice-item-description">{{ $detail['description'] }}</div>@endif
                        @if(!empty($detail['colors']))<div class="invoice-item-description">{{ $labels['colors'] }}: {{ implode(' / ', $detail['colors']) }}</div>@endif
                        <div class="invoice-item-description">{{ $labels['unit_price'] }}: {{ $money($detail['unit_price']) }} · {{ $labels['discount'] }}: {{ $money($detail['discount']) }}</div>
                    </td>
                    <td class="invoice-total">{{ $money($detail['subtotal']) }}</td>
                </tr>
                @foreach($detail['services'] as $service)
                    <tr class="invoice-service">
                        <td class="invoice-qty">{{ $service['quantity'] }}</td>
                        <td>
                            {{ $service['name'] }}
                            @if($service['detail'])<div class="invoice-item-description">{{ $service['detail'] }}</div>@endif
                            <div class="invoice-item-description">@if(!empty($service['has_approximate_unit_price'])){{ $labels['approximate'] }} @endif{{ $money($service['unit_price']) }} · {{ $labels['discount'] }}: {{ $money($service['discount']) }}</div>
                        </td>
                        <td class="invoice-total">{{ $money($service['subtotal']) }}</td>
                    </tr>
                @endforeach
            @endforeach
            @foreach($services as $service)
                <tr>
                    <td class="invoice-qty">{{ $service['quantity'] }}</td>
                    <td>
                        <strong>{{ $service['name'] }}</strong>
                        @if($service['detail'])<div class="invoice-item-description">{{ $service['detail'] }}</div>@endif
                        <div class="invoice-item-description">@if(!empty($service['has_approximate_unit_price'])){{ $labels['approximate'] }} @endif{{ $money($service['unit_price']) }} · {{ $labels['discount'] }}: {{ $money($service['discount']) }}</div>
                    </td>
                    <td class="invoice-total">{{ $money($service['subtotal']) }}</td>
                </tr>
            @endforeach
            <tr class="invoice-summary">
                <td></td>
                <td>{{ $labels['subtotal'] }}</td>
                <td class="invoice-total">{{ $money($factura->subtotal) }}</td>
            </tr>
            @if((float) $factura->descuento)
                <tr class="invoice-summary">
                    <td></td>
                    <td>{{ $labels['discount'] }}</td>
                    <td class="invoice-total">- {{ $money($factura->descuento) }}</td>
                </tr>
            @endif
            @if((float) $factura->monto_iva)
                <tr class="invoice-summary">
                    <td></td>
                    <td>{{ $labels['vat'] }}</td>
                    <td class="invoice-total">{{ $money($factura->monto_iva) }}</td>
                </tr>
            @endif
            @if((float) $factura->monto_ir)
                <tr class="invoice-summary">
                    <td></td>
                    <td>{{ $labels['income_tax'] }}</td>
                    <td class="invoice-total">- {{ $money(abs($factura->monto_ir)) }}</td>
                </tr>
            @endif
            <tr class="invoice-grand-total">
                <td></td>
                <td>{{ $labels['total_to_pay'] }}</td>
                <td class="invoice-total">{{ $money($factura->total) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="invoice-footer">
        <tr>
            <td class="invoice-notes">
                <div>{{ $labels['seller'] }}: {{ $factura->usuario?->nombre ?? '—' }}</div>
                <div>{{ $labels['channel'] }}: {{ $channelLabel }}</div>
                <div>{{ $labels['payment_method'] }}: {{ $paymentMethodLabel }}</div>
                <div>{{ $labels['origin'] }}: {{ $factura->origen === 'pedido' ? $labels['order'] . ' ' . ($factura->pedido?->numero ?? '') : $labels['direct_sale'] }}</div>
                <div style="margin-top:8px;">{{ $labels['thank_you'] }}</div>
            </td>
            <td class="invoice-signature">
                @if(!empty($company['firma']))<img src="{{ $company['firma'] }}">@endif
                @if(!empty($company['sello']))<img src="{{ $company['sello'] }}">@endif
            </td>
        </tr>
    </table>
    <div class="invoice-bottom-rule"></div>
@endsection
