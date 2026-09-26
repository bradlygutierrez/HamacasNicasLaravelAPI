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
        'usuario_id',
        'ubicacion_id',
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

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class, 'inventario_hamaca_id');
    }

    public function detalleFacturas()
    {
        return $this->hasMany(DetalleFactura::class, 'inventario_hamaca_id');
    }
}
