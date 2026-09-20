<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DetalleFacturaServicio extends Model { protected $table = 'detalle_factura_servicios'; protected $guarded = ['id']; protected $casts = ['cantidad'=>'decimal:4','precio_unitario'=>'decimal:2','descuento'=>'decimal:2','subtotal'=>'decimal:2']; public function detalleFactura(){return $this->belongsTo(DetalleFactura::class);} }
