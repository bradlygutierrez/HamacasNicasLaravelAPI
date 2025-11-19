<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class HamacaColor extends Model
{
    /** @use HasFactory<\Database\Factories\HamacaColorFactory> */
    use HasFactory;
	protected $table = 'hamaca_color';
    protected $fillable = ['hamaca_id', 'color_id'];

}
