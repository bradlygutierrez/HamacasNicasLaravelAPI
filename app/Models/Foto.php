<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Foto extends Model
{
    use HasFactory;

    protected $table = 'fotos';

    protected $fillable = [
        'ruta'
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function hamacas()
    {
        return $this->belongsToMany(
            Hamaca::class,
            'hamaca_foto',
            'foto_id',
            'hamaca_id'
        )->withTimestamps();
    }
}
