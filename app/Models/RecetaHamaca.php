<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecetaHamaca extends Model
{
    use HasFactory;

    protected $table = 'recetas_hamaca';

    protected $fillable = [
        'hamaca_id',
        'hamaca_variante_id',
        'version',
        'estado',
        'observaciones',
        'usuario_id',
        'activado_por_id',
        'activated_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'activated_at' => 'datetime',
    ];

    public function hamaca()
    {
        return $this->belongsTo(Hamaca::class);
    }

    public function hamacaVariante()
    {
        return $this->belongsTo(HamacaVariante::class, 'hamaca_variante_id');
    }

    public function detallesMateriales()
    {
        return $this->hasMany(RecetaMaterial::class);
    }

    public function detallesManoObra()
    {
        return $this->hasMany(RecetaManoObra::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function activadoPor()
    {
        return $this->belongsTo(Usuario::class, 'activado_por_id');
    }
}
