<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaMaterialSnapshot extends Model
{
    protected $table = 'proforma_materiales_snapshot';
    protected $guarded = ['id'];
    protected $casts = ['cantidad_base_unitaria' => 'decimal:4', 'factor_cantidad' => 'decimal:4', 'porcentaje_merma' => 'decimal:2', 'cantidad_total_con_merma' => 'decimal:4', 'contenido_por_compra_snapshot' => 'decimal:4', 'precio_compra_snapshot' => 'decimal:2', 'costo_unidad_consumo_snapshot' => 'decimal:6', 'costo_consumo_total' => 'decimal:2'];
    public function proforma() { return $this->belongsTo(Proforma::class); }
}
