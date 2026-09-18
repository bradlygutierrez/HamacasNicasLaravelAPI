<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialPrecio extends Model
{
    use HasFactory;

    protected $table = 'material_precios';

    protected $fillable = [
        'material_id',
        'precio',
        'fecha',
        'usuario_id',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'fecha' => 'datetime',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
