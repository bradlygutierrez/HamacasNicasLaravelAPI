<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicioMaterial extends Model
{
    use HasFactory;

    protected $table = 'servicio_materiales';
    protected $fillable = ['servicio_adicional_id', 'material_id', 'cantidad', 'porcentaje_merma'];
    protected $casts = ['cantidad' => 'decimal:4', 'porcentaje_merma' => 'decimal:2'];

    public function servicio()
    {
        return $this->belongsTo(ServicioAdicional::class, 'servicio_adicional_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
