<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    /** @use HasFactory<\Database\Factories\FacturaFactory> */
    use HasFactory;

	 protected $fillable = [
        'numero',
        'cliente_id',
        'vendedor_id',
        'canal',
        'nombre_cliente',
        'ruc',
        'direccion',
        'telefono',
        'correo',
        'metodo_pago',
        'subtotal',
        'descuento',
        'tasa_iva',
        'monto_iva',
        'aplica_ir',
        'tasa_ir',
        'monto_ir',
        'total',
        'fecha',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'vendedor_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleFactura::class);
    }

}
