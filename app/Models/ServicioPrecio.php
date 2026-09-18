<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicioPrecio extends Model
{
    use HasFactory;

    protected $table = 'servicio_precios';

    protected $fillable = [
        'servicio_adicional_id',
        'precio_venta',
        'costo',
        'fecha',
        'usuario_id',
    ];

    protected $casts = [
        'precio_venta' => 'decimal:2',
        'costo' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function servicioAdicional()
    {
        return $this->belongsTo(ServicioAdicional::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
