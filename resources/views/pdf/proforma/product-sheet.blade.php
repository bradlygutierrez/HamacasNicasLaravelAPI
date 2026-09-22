<div class="sheet-header">
    <table>
        <tr>
            <td style="width:55%; vertical-align:top;">
                @if(!empty($company['logo'])) <img src="{{ $company['logo'] }}" style="max-width:130px; max-height:42px;"> @else <strong class="blue" style="font-size:15px;">{{ $company['nombre'] }}</strong> @endif
            </td>
            <td class="muted small" style="text-align:right; vertical-align:top;">{{ $company['web'] ?? '' }}<br>{{ $company['facebook'] ?? '' }} @if(!empty($company['facebook']) && !empty($company['instagram'])) · @endif {{ $company['instagram'] ?? '' }} @if(!empty($company['youtube']))<br>{{ $company['youtube'] }}@endif</td>
        </tr>
    </table>
</div>
<div class="blue" style="font-size:23px; font-weight:bold;">{{ $sheet['name'] }}</div>
@if($sheet['variant'] || $sheet['colors']) <div class="muted" style="font-size:12px; margin-top:3px;">{{ $sheet['variant'] ?? '' }} @if($sheet['variant'] && $sheet['colors']) · @endif {{ implode(' / ', $sheet['colors']) }}</div> @endif
<div style="margin-top:18px; text-align:center;">
    @if(!empty($sheet['photos'][0])) <img class="sheet-photo-main" src="{{ $sheet['photos'][0] }}"> @else <div style="height:250px; border:1px solid #d9e0e4; color:#63717a; padding-top:120px; text-align:center;">Imagen no disponible</div> @endif
</div>
@if(count($sheet['photos']) > 1)<table style="margin-top:12px;"><tr>@foreach(array_slice($sheet['photos'], 1, 2) as $photo)<td style="width:50%; text-align:center;"><img class="sheet-photo-small" src="{{ $photo }}"></td>@endforeach</tr></table>@endif
<h2 class="section-title">Características</h2>
<table class="avoid-break"><tr><td style="width:50%; vertical-align:top;"><ul style="margin:0; padding-left:15px;">@foreach(array_filter(['Categoría' => $sheet['category'], 'Tamaño' => $sheet['size'], 'Variante' => $sheet['variant'], 'Colores' => implode(' / ', $sheet['colors'])]) as $label => $value)<li><strong>{{ $label }}:</strong> {{ $value }}</li>@endforeach</ul></td><td style="vertical-align:top;">{{ $sheet['description'] ?: 'Sin descripción disponible.' }}</td></tr></table>
<p style="margin-top:16px;"><strong class="blue">Cantidad cotizada:</strong> {{ $sheet['quantity'] }}</p>
<div class="footer">{{ $company['correo'] ?? '' }} @if(!empty($company['correo']) && !empty($company['telefono'])) · @endif {{ $company['telefono'] ?? '' }} @if(!empty($company['web'])) · {{ $company['web'] }} @endif</div>
