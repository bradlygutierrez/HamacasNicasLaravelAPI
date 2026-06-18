<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    use HasFactory;

    protected $table = 'permisos';

    protected $fillable = [
        'nombre',
        'slug',
        'descripcion',
    ];

    public function pantallas()
    {
        return $this->belongsToMany(
            Pantalla::class,
            'pantalla_permiso_roles',
            'permiso_id',
            'pantalla_id'
        )->withPivot('rol')->withTimestamps();
    }

    public function pantallasPorRol()
    {
        return $this->hasMany(PantallaPermisoRol::class);
    }
}
