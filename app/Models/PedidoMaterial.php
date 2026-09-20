<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PedidoMaterial extends Model { protected $fillable=['pedido_id','material_id','material_nombre_snapshot','unidad_consumo_snapshot','unidad_compra_snapshot','cantidad_requerida','contenido_por_compra_snapshot','cantidad_compra_plan','precio_compra_snapshot','costo_consumo_estimado','costo_compra_estimado','estado','cantidad_compra_real','costo_compra_real','observaciones','listo_at','actualizado_por_id']; protected $casts=['listo_at'=>'datetime']; public function pedido(){return $this->belongsTo(Pedido::class);} }
