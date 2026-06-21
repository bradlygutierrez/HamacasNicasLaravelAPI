<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HamacaVariante extends Model
{
    protected $table = 'hamaca_variantes';

    protected $fillable = [
        'hamaca_id',
        'nombre',
        'composicion_clave',
        'state',
    ];

    protected $casts = [
        'state' => 'boolean',
    ];

    public function hamaca()
    {
        return $this->belongsTo(Hamaca::class, 'hamaca_id');
    }

    public function colores()
    {
        return $this->belongsToMany(
            Color::class,
            'hamaca_variante_color',
            'hamaca_variante_id',
            'color_id'
        )->withTimestamps();
    }

    public function fotos()
    {
        return $this->belongsToMany(
            Foto::class,
            'hamaca_variante_foto',
            'hamaca_variante_id',
            'foto_id'
        )->withTimestamps();
    }

    public function inventarios()
    {
        return $this->hasMany(InventarioHamaca::class, 'hamaca_variante_id');
    }
}