<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    /** @use HasFactory<\Database\Factories\ColorFactory> */
    use HasFactory;

	protected $fillable = ['nombre'];
    protected $table = 'colores';
    
    public function hamacas()
    {
        return $this->belongsToMany(\App\Models\Hamaca::class, 'hamaca_color', 'color_id', 'hamaca_id')
                    ->withTimestamps();
    }

}
