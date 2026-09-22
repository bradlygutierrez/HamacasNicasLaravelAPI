<?php

return [
    // Rates are configured as percentages and stored on invoices as fractions.
    'iva_rate' => env('COMMERCIAL_IVA_RATE', '15'),
    'ir_rate' => env('COMMERCIAL_IR_RATE', '2'),
];
