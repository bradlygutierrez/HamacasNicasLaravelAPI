<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleFactura extends Model
{
    /** @use HasFactory<\Database\Factories\DetalleFacturaFactory> */
    use HasFactory;

	protected $fillable = [
        'factura_id',
        'hamaca_id',
        'cantidad',
        'precio_unitario',
    ];

    public function factura()
    {
        return $this->belongsTo(Factura::class);
    }

    public function hamaca()
    {
        return $this->belongsTo(Hamaca::class);
    }

}
