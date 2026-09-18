<?php
namespace App\Http\Controllers\API\V1;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ClienteResource;
use App\Models\Cliente;
use Illuminate\Http\Request;
class ClienteController extends Controller { public function index(Request $request) { $perPage = min(max($request->integer('per_page', 15), 1), 100); $clients = Cliente::query()->where('state', true)->when($request->filled('search'), function ($query) use ($request): void { $term = '%' . $request->string('search') . '%'; $query->where(fn ($q) => $q->where('nombre', 'like', $term)->orWhere('ruc', 'like', $term)->orWhere('telefono', 'like', $term)->orWhere('correo', 'like', $term)); })->orderBy('nombre')->paginate($perPage); return ClienteResource::collection($clients); } }
