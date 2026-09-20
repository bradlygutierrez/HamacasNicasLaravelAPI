<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Resources\Json\JsonResource;
class PedidoDetalleServicioResource extends JsonResource { public function toArray($request): array { $internal = in_array($request->user()?->rol, ['admin','socio'], true); $data = ['id'=>$this->id,'nombre'=>$this->servicio_nombre_snapshot,'detalle'=>$this->detalle,'cantidad'=>$this->cantidad,'precio_unitario'=>$this->precio_unitario,'descuento'=>$this->descuento,'subtotal'=>$this->subtotal]; if ($internal) $data += ['costo_base_unitario_override'=>$this->costo_base_unitario_override,'costo_base_unitario_snapshot'=>$this->costo_base_unitario_snapshot,'costo_unitario_estimado'=>$this->costo_unitario_estimado,'costo_total_estimado'=>$this->costo_total_estimado]; return $data; } }
