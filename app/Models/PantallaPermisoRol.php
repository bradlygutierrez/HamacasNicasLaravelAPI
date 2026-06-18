<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PantallaPermisoRol extends Model
{
    use HasFactory;

    public const ROLES = ['admin', 'vendedor', 'almacenista', 'socio'];

    protected $table = 'pantalla_permiso_roles';

    protected $fillable = [
        'pantalla_id',
        'permiso_id',
        'rol',
    ];

    public function pantalla()
    {
        return $this->belongsTo(Pantalla::class);
    }

    public function permiso()
    {
        return $this->belongsTo(Permiso::class);
    }
}
