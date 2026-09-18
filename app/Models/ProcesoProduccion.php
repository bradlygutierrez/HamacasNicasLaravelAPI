<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcesoProduccion extends Model
{
    use HasFactory;

    protected $table = 'procesos_produccion';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'state',
    ];

    protected $casts = [
        'state' => 'boolean',
    ];
}
