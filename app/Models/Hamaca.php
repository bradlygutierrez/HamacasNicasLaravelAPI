<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hamaca extends Model
{
    /** @use HasFactory<\Database\Factories\HamacaFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'hamacas';

    protected $fillable = [
        'nombre',
        'descripcion',
        'categoria_id',
        'tamano_id',
        'precio',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'deleted_at' => 'datetime',
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

    public function variantes()
    {
        return $this->hasMany(HamacaVariante::class, 'hamaca_id');
    }
}
