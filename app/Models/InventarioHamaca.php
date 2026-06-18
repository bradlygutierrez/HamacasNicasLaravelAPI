<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventarioHamaca extends Model
{
    use HasFactory;

    protected $table = 'inventario_hamacas';

    protected $fillable = [
        'hamaca_id',
        'ubicacion_id',
        'usuario_id',
        'composicion_clave',
        'cantidad',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function hamaca()
    {
        return $this->belongsTo(Hamaca::class);
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function colores()
    {
        return $this->belongsToMany(
            Color::class,
            'inventario_hamaca_color',
            'inventario_hamaca_id',
            'color_id'
        )->withTimestamps();
    }
}
