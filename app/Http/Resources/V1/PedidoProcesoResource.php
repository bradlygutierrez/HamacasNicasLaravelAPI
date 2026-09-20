<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Resources\Json\JsonResource;
class PedidoProcesoResource extends JsonResource { public function toArray($request): array { $internal = in_array($request->user()?->rol, ['admin','socio'], true); $data = ['id'=>$this->id,'nombre'=>$this->proceso_nombre_snapshot,'orden'=>$this->orden,'estado'=>$this->estado,'iniciado_at'=>$this->iniciado_at,'completado_at'=>$this->completado_at,'observaciones'=>$this->observaciones]; if ($internal) $data['costo_estimado'] = $this->costo_estimado; return $data; } }
