<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecetaManoObra extends Model
{
    use HasFactory;

    protected $table = 'receta_mano_obra';

    protected $fillable = ['receta_hamaca_id', 'proceso_produccion_id', 'costo_unitario', 'orden'];

    protected $casts = [
        'costo_unitario' => 'decimal:2',
        'orden' => 'integer',
    ];

    public function receta()
    {
        return $this->belongsTo(RecetaHamaca::class, 'receta_hamaca_id');
    }

    public function proceso()
    {
        return $this->belongsTo(ProcesoProduccion::class, 'proceso_produccion_id');
    }
}
