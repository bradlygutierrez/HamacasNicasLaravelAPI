<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicioManoObra extends Model
{
    use HasFactory;

    protected $table = 'servicio_mano_obra';
    protected $fillable = ['servicio_adicional_id', 'proceso_produccion_id', 'costo_unitario', 'orden'];
    protected $casts = ['costo_unitario' => 'decimal:2', 'orden' => 'integer'];

    public function servicio()
    {
        return $this->belongsTo(ServicioAdicional::class, 'servicio_adicional_id');
    }

    public function proceso()
    {
        return $this->belongsTo(ProcesoProduccion::class, 'proceso_produccion_id');
    }
}
