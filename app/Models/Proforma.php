<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proforma extends Model
{
    use HasFactory;

    protected $fillable = ['numero', 'cliente_id', 'vendedor_id', 'estado', 'nombre_cliente', 'ruc', 'direccion', 'telefono', 'correo', 'fecha', 'valida_hasta', 'observaciones', 'subtotal_productos', 'subtotal_servicios', 'subtotal_bruto', 'descuento_global', 'descuento_lineas', 'descuento_total', 'base_neta', 'aplica_iva', 'tasa_iva', 'monto_iva', 'aplica_ir', 'tasa_ir', 'monto_ir', 'tasa_comision_vendedor', 'monto_comision_vendedor', 'costo_materiales_estimado', 'costo_mano_de_obra_estimado', 'costo_servicios_base_estimado', 'costo_total_estimado', 'costo_compra_estimado', 'utilidad_estimada', 'total', 'emitida_at'];

    protected $casts = ['fecha' => 'date', 'valida_hasta' => 'date', 'emitida_at' => 'datetime', 'aplica_iva' => 'boolean', 'aplica_ir' => 'boolean'];

    public function cliente() { return $this->belongsTo(Cliente::class); }
    public function vendedor() { return $this->belongsTo(Usuario::class, 'vendedor_id'); }
    public function detalles() { return $this->hasMany(ProformaDetalle::class); }
    public function servicios() { return $this->hasMany(ProformaServicio::class); }
    public function materialesSnapshot() { return $this->hasMany(ProformaMaterialSnapshot::class); }
    public function manoObraSnapshot() { return $this->hasMany(ProformaManoObraSnapshot::class); }
    public function pedido() { return $this->hasOne(Pedido::class); }
}
