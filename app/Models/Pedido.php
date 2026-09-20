<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Pedido extends Model
{
    protected $table = 'pedidos';
    protected $fillable = ['proforma_id','numero','proforma_numero_snapshot','cliente_id','vendedor_id','creado_por_id','estado','nombre_cliente','ruc','direccion','telefono','correo','fecha_pedido','fecha_entrega_estimada','fecha_inicio_produccion','fecha_terminado','observaciones_cliente','observaciones_internas','subtotal_productos','subtotal_servicios','subtotal_bruto','descuento_global','descuento_lineas','descuento_total','base_neta','aplica_iva','tasa_iva','monto_iva','aplica_ir','tasa_ir','monto_ir','tasa_comision_vendedor','monto_comision_vendedor','costo_materiales_estimado','costo_mano_obra_estimado','costo_servicios_base_estimado','costo_total_estimado','costo_compra_estimado','utilidad_estimada','total','cancelado_at','cancelado_por_id','motivo_cancelacion'];
    protected $casts = ['fecha_pedido'=>'date','fecha_entrega_estimada'=>'date','fecha_inicio_produccion'=>'datetime','fecha_terminado'=>'datetime','cancelado_at'=>'datetime','aplica_iva'=>'boolean','aplica_ir'=>'boolean'];
    public function proforma(){return $this->belongsTo(Proforma::class);} public function cliente(){return $this->belongsTo(Cliente::class);} public function vendedor(){return $this->belongsTo(Usuario::class,'vendedor_id');} public function creador(){return $this->belongsTo(Usuario::class,'creado_por_id');} public function canceladoPor(){return $this->belongsTo(Usuario::class,'cancelado_por_id');} public function detalles(){return $this->hasMany(PedidoDetalle::class);} public function servicios(){return $this->hasMany(PedidoServicio::class);} public function materiales(){return $this->hasMany(PedidoMaterial::class);} public function procesos(){return $this->hasMany(PedidoProceso::class);} public function historial(){return $this->hasMany(PedidoHistorialEstado::class);}
}
