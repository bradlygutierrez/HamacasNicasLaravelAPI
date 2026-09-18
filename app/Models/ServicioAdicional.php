<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicioAdicional extends Model
{
    use HasFactory;

    protected $table = 'servicios_adicionales';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'alcance',
        'metodo_calculo',
        'unidad',
        'precio_venta_actual',
        'costo_actual',
        'state',
    ];

    protected $casts = [
        'precio_venta_actual' => 'decimal:2',
        'costo_actual' => 'decimal:2',
        'state' => 'boolean',
    ];

    public function precios()
    {
        return $this->hasMany(ServicioPrecio::class);
    }
}
