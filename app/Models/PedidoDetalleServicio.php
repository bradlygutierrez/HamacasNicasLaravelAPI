<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PedidoDetalleServicio extends Model { protected $fillable=['pedido_detalle_id','proforma_detalle_servicio_id','servicio_adicional_id','servicio_nombre_snapshot','alcance_snapshot','metodo_calculo_snapshot','unidad_snapshot','detalle','cantidad','precio_unitario','descuento','subtotal','costo_base_unitario_override','costo_base_unitario_snapshot','costo_unitario_estimado','costo_total_estimado']; public function detalle(){return $this->belongsTo(PedidoDetalle::class,'pedido_detalle_id');} }
