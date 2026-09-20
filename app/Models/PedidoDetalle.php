<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PedidoDetalle extends Model { protected $fillable=['pedido_id','proforma_detalle_id','hamaca_id','hamaca_variante_id','receta_hamaca_id','receta_version_snapshot','hamaca_nombre_snapshot','hamaca_descripcion_snapshot','cantidad','precio_unitario','descuento','subtotal','costo_unitario_estimado','costo_total_estimado']; public function pedido(){return $this->belongsTo(Pedido::class);} public function servicios(){return $this->hasMany(PedidoDetalleServicio::class);} }
