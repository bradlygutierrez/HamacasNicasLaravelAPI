<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaManoObraSnapshot extends Model
{
    protected $table = 'proforma_mano_obra_snapshot';
    protected $guarded = ['id'];
    protected $casts = ['costo_unitario_snapshot' => 'decimal:2', 'factor_cantidad' => 'decimal:4', 'costo_total' => 'decimal:2', 'orden' => 'integer'];
    public function proforma() { return $this->belongsTo(Proforma::class); }
}
