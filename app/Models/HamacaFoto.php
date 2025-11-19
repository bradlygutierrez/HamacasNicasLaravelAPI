<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HamacaFoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'hamaca_id',
        'ruta_foto',
        'descripcion',
    ];

    public function hamaca()
    {
        return $this->belongsTo(Hamaca::class);
    }
}
