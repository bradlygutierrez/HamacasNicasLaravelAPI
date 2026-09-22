<table class="top-rule" style="padding-top:10px; margin-bottom:18px;">
    <tr>
        <td style="width:48%; vertical-align:top;">
            @if(!empty($company['logo'])) <img src="{{ $company['logo'] }}" style="max-width:145px; max-height:50px;"> @else <div class="blue" style="font-size:18px; font-weight:bold;">{{ $company['nombre'] }}</div> @endif
            <div class="muted small">{{ $company['web'] ?? '' }} @if(!empty($company['web']) && !empty($company['correo'])) · @endif {{ $company['correo'] ?? '' }}</div>
            <div class="muted small">{{ $company['facebook'] ?? '' }} @if(!empty($company['facebook']) && !empty($company['instagram'])) · @endif {{ $company['instagram'] ?? '' }}</div>
        </td>
        <td style="width:27%; text-align:center; vertical-align:top;">@yield('document-heading')</td>
        <td style="width:25%; text-align:right; vertical-align:top;">@yield('document-meta')</td>
    </tr>
</table>
