<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Model;

class UsuarioHamaca extends Model
{
    /** @use HasFactory<\Database\Factories\UsuarioHamacaFactory> */
    use HasFactory;

	protected $table = 'usuario_hamaca';
    protected $fillable = ['usuario_id', 'hamaca_id'];

}
