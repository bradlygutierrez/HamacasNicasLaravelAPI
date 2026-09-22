<?php

return [
    'moneda' => env('HAMACAS_MONEDA', 'C$'),
    'empresa' => [
        'nombre' => env('HAMACAS_EMPRESA_NOMBRE', 'Hamacas Nica'),
        'ruc' => env('HAMACAS_EMPRESA_RUC'),
        'direccion' => env('HAMACAS_EMPRESA_DIRECCION'),
        'ciudad' => env('HAMACAS_EMPRESA_CIUDAD'),
        'pais' => env('HAMACAS_EMPRESA_PAIS'),
        'telefono' => env('HAMACAS_EMPRESA_TELEFONO'),
        'correo' => env('HAMACAS_EMPRESA_CORREO'),
        'web' => env('HAMACAS_EMPRESA_WEB'),
        'facebook' => env('HAMACAS_EMPRESA_FACEBOOK'),
        'instagram' => env('HAMACAS_EMPRESA_INSTAGRAM'),
        'youtube' => env('HAMACAS_EMPRESA_YOUTUBE'),
        'logo' => env('HAMACAS_EMPRESA_LOGO'),
        'firma' => env('HAMACAS_EMPRESA_FIRMA'),
        'sello' => env('HAMACAS_EMPRESA_SELLO'),
    ],
    'proforma_condiciones_pago' => env('PROFORMA_CONDICIONES_PAGO'),
];
