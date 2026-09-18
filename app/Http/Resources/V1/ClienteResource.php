<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Resources\Json\JsonResource;
class ClienteResource extends JsonResource { public function toArray($request): array { return ['id' => $this->id, 'nombre' => $this->nombre, 'ruc' => $this->ruc, 'direccion' => $this->direccion, 'telefono' => $this->telefono, 'correo' => $this->correo]; } }
