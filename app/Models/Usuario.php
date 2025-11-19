<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    /** @use HasFactory<\Database\Factories\UsuarioFactory> */
    use HasFactory;
	protected $fillable = [
        'nombre',
        'correo',
        'contraseña',
        'rol',
    ];

    // Relación muchos a muchos con Hamaca
    public function hamacas()
    {
        return $this->belongsToMany(\App\Models\Hamaca::class, 'usuario_hamaca', 'usuario_id', 'hamaca_id')
                ->withTimestamps();
    }

    // Relación uno a muchos con Movimientos
    public function movimientos()
    {
        return $this->hasMany(Movimiento::class);
    }

    // Relación uno a muchos con Facturas
    public function facturas()
    {
        return $this->hasMany(Factura::class);
    }
	
}
