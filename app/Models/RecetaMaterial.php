<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecetaMaterial extends Model
{
    use HasFactory;

    protected $table = 'receta_materiales';

    protected $fillable = ['receta_hamaca_id', 'material_id', 'cantidad', 'porcentaje_merma'];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'porcentaje_merma' => 'decimal:2',
    ];

    public function receta()
    {
        return $this->belongsTo(RecetaHamaca::class, 'receta_hamaca_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
