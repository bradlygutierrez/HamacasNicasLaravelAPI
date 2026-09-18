<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaServicio extends Model
{
    protected $table = 'proforma_servicios';
    protected $guarded = ['id'];
    protected $casts = ['cantidad' => 'decimal:4', 'precio_unitario' => 'decimal:2', 'descuento' => 'decimal:2', 'subtotal' => 'decimal:2', 'costo_base_unitario_override' => 'decimal:2', 'costo_base_unitario_snapshot' => 'decimal:2', 'costo_unitario_estimado' => 'decimal:2', 'costo_total_estimado' => 'decimal:2'];
    public function proforma() { return $this->belongsTo(Proforma::class); }
    public function servicio() { return $this->belongsTo(ServicioAdicional::class, 'servicio_adicional_id'); }
}
