<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tamano extends Model
{
    /** @use HasFactory<\Database\Factories\TamanoFactory> */
    use HasFactory;

	 protected $fillable = ['nombre', 'descripcion'];

    public function hamacas()
    {
        return $this->hasMany(Hamaca::class);
    }

}
