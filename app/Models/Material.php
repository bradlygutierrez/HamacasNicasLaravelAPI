<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $table = 'materiales';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'unidad_consumo',
        'unidad_compra',
        'contenido_por_compra',
        'precio_actual',
        'porcentaje_merma',
        'state',
    ];

    protected $casts = [
        'contenido_por_compra' => 'decimal:4',
        'precio_actual' => 'decimal:2',
        'porcentaje_merma' => 'decimal:2',
        'state' => 'boolean',
    ];

    public function precios()
    {
        return $this->hasMany(MaterialPrecio::class);
    }

    public function recetas()
    {
        return $this->hasMany(RecetaMaterial::class);
    }

    public function servicios()
    {
        return $this->hasMany(ServicioMaterial::class);
    }
}
