<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    /** @use HasFactory<\Database\Factories\ColorFactory> */
    use HasFactory;

	protected $fillable = ['nombre'];
    protected $table = 'colores';

    public function inventarios()
    {
        return $this->belongsToMany(
            InventarioHamaca::class,
            'inventario_hamaca_color',
            'color_id',
            'inventario_hamaca_id'
        )->withTimestamps();
    }

}
