<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pantalla extends Model
{
    use HasFactory;

    protected $table = 'pantallas';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
        'ruta',
        'icono',
        'orden',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'state' => 'boolean',
        ];
    }

    public function permisos()
    {
        return $this->belongsToMany(
            Permiso::class,
            'pantalla_permiso_roles',
            'pantalla_id',
            'permiso_id'
        )->withPivot('rol')->withTimestamps();
    }

    public function permisosPorRol()
    {
        return $this->hasMany(PantallaPermisoRol::class);
    }
}
