<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    /** @use HasFactory<\Database\Factories\MovimientoFactory> */
    use HasFactory;

	protected $fillable = [
        'inventario_hamaca_id',
        'usuario_id',
        'factura_id',
        'ubicacion_origen_id',
        'ubicacion_destino_id',
        'tipo', // entrada o salida
        'cantidad',
        'fecha',
    ];

    public function inventarioHamaca()
    {
        return $this->belongsTo(InventarioHamaca::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

}
