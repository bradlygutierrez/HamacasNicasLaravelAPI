<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FacturaServicio extends Model { protected $table = 'factura_servicios'; protected $guarded = ['id']; protected $casts = ['cantidad'=>'decimal:4','precio_unitario'=>'decimal:2','descuento'=>'decimal:2','subtotal'=>'decimal:2']; public function factura(){return $this->belongsTo(Factura::class);} }
