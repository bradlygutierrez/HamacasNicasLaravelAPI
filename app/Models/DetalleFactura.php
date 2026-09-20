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
        'pedido_detalle_id',
        'inventario_hamaca_id',
        'hamaca_id',
        'usuario_id',
        'ubicacion_id',
        'hamaca_nombre',
        'hamaca_descripcion',
        'colores_snapshot',
        'cantidad',
        'precio_unitario',
        'descuento',
        'subtotal',
    ];

    public function factura()
    {
        return $this->belongsTo(Factura::class);
    }

    public function inventarioHamaca()
    {
        return $this->belongsTo(InventarioHamaca::class);
    }

    public function hamaca()
    {
        return $this->belongsTo(Hamaca::class)->withTrashed();
    }

    public function servicios() { return $this->hasMany(DetalleFacturaServicio::class); }

}
