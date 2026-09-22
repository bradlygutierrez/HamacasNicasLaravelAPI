<div class="footer">
    @if(!empty($company['correo']) || !empty($company['telefono'])) Si tiene alguna duda, contáctenos. @endif
    {{ $company['correo'] ?? '' }} @if(!empty($company['correo']) && !empty($company['telefono'])) · @endif {{ $company['telefono'] ?? '' }}
    @if(!empty($company['web'])) · {{ $company['web'] }} @endif
</div>
