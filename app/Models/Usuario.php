<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nombre',
        'correo',
        'password',
        'foto',
        'rol',
        'state',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'state' => 'boolean',
        ];
    }

    public function inventarios()
    {
        return $this->hasMany(InventarioHamaca::class);
    }

    public function movimientos()
    {
        return $this->hasMany(Movimiento::class);
    }

    public function facturas()
    {
        return $this->hasMany(Factura::class);
    }

    public function accesosPorRol()
    {
        return $this->hasMany(PantallaPermisoRol::class, 'rol', 'rol');
    }
}
