<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaServicioDesglose extends Model
{
    protected $table = 'proforma_servicio_desgloses';
    protected $guarded = ['id'];
    protected $casts = ['monto' => 'decimal:2', 'orden' => 'integer'];

    public function servicioPedido() { return $this->belongsTo(ProformaServicio::class, 'proforma_servicio_id'); }
    public function servicioProducto() { return $this->belongsTo(ProformaDetalleServicio::class, 'proforma_detalle_servicio_id'); }
}
