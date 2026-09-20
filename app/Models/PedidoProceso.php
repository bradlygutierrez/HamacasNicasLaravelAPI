<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PedidoProceso extends Model { protected $fillable=['pedido_id','proceso_produccion_id','proceso_nombre_snapshot','orden','costo_estimado','estado','iniciado_at','completado_at','observaciones','actualizado_por_id']; protected $casts=['iniciado_at'=>'datetime','completado_at'=>'datetime']; public function pedido(){return $this->belongsTo(Pedido::class);} }
