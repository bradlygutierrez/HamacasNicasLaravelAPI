<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PedidoDetalle extends Model { protected $table = 'pedido_detalles'; protected $fillable=['pedido_id','proforma_detalle_id','hamaca_id','receta_hamaca_id','receta_version_snapshot','hamaca_nombre_snapshot','hamaca_descripcion_snapshot','cantidad','precio_unitario','descuento','subtotal','costo_unitario_estimado','costo_total_estimado']; public function pedido(){return $this->belongsTo(Pedido::class);} public function hamaca(){return $this->belongsTo(Hamaca::class)->withTrashed();} public function servicios(){return $this->hasMany(PedidoDetalleServicio::class);} }
