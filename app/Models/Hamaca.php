<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hamaca extends Model
{
    /** @use HasFactory<\Database\Factories\HamacaFactory> */
    use HasFactory;

	protected $fillable = [
        'nombre',
        'descripcion',
        'categoria_id',
        'ubicacion_id',
        'tamano_id',
        'cantidad',
        'precio',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function tamano()
    {
        return $this->belongsTo(Tamano::class);
    }

    public function usuarios()
    {
        return $this->belongsToMany(\App\Models\Usuario::class, 'usuario_hamaca', 'hamaca_id', 'usuario_id')
                ->withTimestamps();
    }

    public function colores()
    {
        return $this->belongsToMany(
            \App\Models\Color::class,
            'hamaca_color',
            'hamaca_id',
            'color_id'
        )->withTimestamps();    
    }


    public function movimientos()
    {
        return $this->hasMany(Movimiento::class);
    }

    public function detalleFacturas()
    {
        return $this->hasMany(DetalleFactura::class);
    }

    public function fotos()
    {
        return $this->hasMany(HamacaFoto::class);
    }


}
