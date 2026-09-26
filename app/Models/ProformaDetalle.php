<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaDetalle extends Model
{
    protected $table = 'proforma_detalles';
    protected $guarded = ['id'];
    protected $casts = ['cantidad' => 'integer', 'precio_unitario' => 'decimal:2', 'descuento' => 'decimal:2', 'subtotal' => 'decimal:2', 'costo_unitario_estimado' => 'decimal:2', 'costo_total_estimado' => 'decimal:2'];
    public function proforma() { return $this->belongsTo(Proforma::class); }
    public function hamaca() { return $this->belongsTo(Hamaca::class); }
    public function receta() { return $this->belongsTo(RecetaHamaca::class, 'receta_hamaca_id'); }
    public function servicios() { return $this->hasMany(ProformaDetalleServicio::class); }
}
