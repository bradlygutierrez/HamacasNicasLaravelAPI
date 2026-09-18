<?php

return [
    'commission_rate' => (float) env('PROFORMA_COMMISSION_RATE', 5.00),
    'iva_rate' => (float) env('PROFORMA_IVA_RATE', 15.00),
    'ir_rate' => (float) env('PROFORMA_IR_RATE', 2.00),
];
