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
        'tamano_id',
        'precio',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }


    public function tamano()
    {
        return $this->belongsTo(Tamano::class);
    }

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class);
    }

    // Inventario
    public function inventarios()
    {
        return $this->hasMany(InventarioHamaca::class);
    }

    public function detalleFacturas()
    {
        return $this->hasMany(DetalleFactura::class);
    }

    public function fotos()
    {
        return $this->belongsToMany(
            Foto::class,
            'hamaca_foto',
            'hamaca_id',
            'foto_id'
        )->withTimestamps();
    }
}
