<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Resources\Json\JsonResource;
class PedidoHistorialResource extends JsonResource { public function toArray($request): array { return ['id'=>$this->id,'estado_anterior'=>$this->estado_anterior,'estado_nuevo'=>$this->estado_nuevo,'usuario_id'=>$this->usuario_id,'comentario'=>$this->comentario,'created_at'=>$this->created_at]; } }
