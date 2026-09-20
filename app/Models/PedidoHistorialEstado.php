<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PedidoHistorialEstado extends Model { protected $table = 'pedido_historial_estados'; public $timestamps=false; protected $fillable=['pedido_id','estado_anterior','estado_nuevo','usuario_id','comentario','created_at']; protected $casts=['created_at'=>'datetime']; public function pedido(){return $this->belongsTo(Pedido::class);} }
