<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;

class DocumentationController extends Controller
{
    public function ui()
    {
        $jsonUrl = url('/api/v1/openapi.json');

        return response()->make(<<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hamacas Nica API Docs</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
  <style>body{margin:0;background:#0f172a} #swagger-ui{max-width:1200px;margin:0 auto}</style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.ui = SwaggerUIBundle({
      url: "{$jsonUrl}",
      dom_id: '#swagger-ui',
      deepLinking: true,
      presets: [SwaggerUIBundle.presets.apis],
      layout: "BaseLayout"
    });
  </script>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']);
    }

    public function json()
    {
        return response()->json($this->spec());
    }

    private function spec(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Hamacas Nica API',
                'version' => '1.0.0',
                'description' => 'Documentación funcional de la API de Hamacas Nica para inventario, POS, facturación y autenticación.',
            ],
            'servers' => [
                ['url' => url('/api')],
            ],
            'tags' => [
                ['name' => 'Auth'],
                ['name' => 'Catalogos'],
                ['name' => 'Hamacas'],
                ['name' => 'Fotos'],
                ['name' => 'Inventario'],
                ['name' => 'Usuarios'],
                ['name' => 'Pantallas'],
                ['name' => 'Permisos'],
                ['name' => 'Movimientos'],
                ['name' => 'Facturas'],
                ['name' => 'POS'],
                ['name' => 'Producción'],
                ['name' => 'Proformas'],
                ['name' => 'Pedidos'],
                ['name' => 'Documentacion'],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum token',
                    ],
                    'apiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                    ],
                ],
                'schemas' => [
                    'MessageResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                        ],
                        'required' => ['message'],
                    ],
                    'LoginRequest' => [
                        'type' => 'object',
                        'required' => ['correo', 'password'],
                        'properties' => [
                            'correo' => ['type' => 'string', 'example' => 'admin@example.com'],
                            'password' => ['type' => 'string', 'example' => 'secret123'],
                        ],
                    ],
                    'LoginResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                            'user' => ['$ref' => '#/components/schemas/Usuario'],
                        ],
                    ],
                    'Usuario' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nombre' => ['type' => 'string'],
                            'correo' => ['type' => 'string'],
                            'rol' => ['type' => 'string'],
                            'state' => ['type' => 'boolean'],
                        ],
                    ],
                    'Pantalla' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nombre' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            'descripcion' => ['type' => 'string', 'nullable' => true],
                            'ruta' => ['type' => 'string', 'nullable' => true],
                            'icono' => ['type' => 'string', 'nullable' => true],
                            'orden' => ['type' => 'integer'],
                            'state' => ['type' => 'boolean'],
                        ],
                    ],
                    'Permiso' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nombre' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            'descripcion' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                    'PantallaPermisoRol' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'pantalla_id' => ['type' => 'integer'],
                            'permiso_id' => ['type' => 'integer'],
                            'rol' => ['type' => 'string', 'enum' => ['admin', 'vendedor', 'almacenista', 'socio']],
                            'pantalla' => ['$ref' => '#/components/schemas/Pantalla'],
                            'permiso' => ['$ref' => '#/components/schemas/Permiso'],
                        ],
                    ],
                    'Categoria' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nombre' => ['type' => 'string'],
                        ],
                    ],
                    'Tamano' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nombre' => ['type' => 'string'],
                        ],
                    ],
                    'Ubicacion' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nombre' => ['type' => 'string'],
                        ],
                    ],
                    'Color' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nombre' => ['type' => 'string'],
                        ],
                    ],
                    'Foto' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'ruta' => ['type' => 'string'],
                        ],
                    ],
                    'Hamaca' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'nombre' => ['type' => 'string'],
                            'descripcion' => ['type' => 'string', 'nullable' => true],
                            'categoria_id' => ['type' => 'integer', 'nullable' => true],
                            'tamano_id' => ['type' => 'integer', 'nullable' => true],
                            'precio' => ['type' => 'number', 'format' => 'float'],
                        ],
                    ],
                    'InventarioHamaca' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'hamaca_id' => ['type' => 'integer'],
                            'usuario_id' => ['type' => 'integer'],
                            'ubicacion_id' => ['type' => 'integer'],
                            'cantidad' => ['type' => 'integer'],
                        ],
                    ],
                    'Movimiento' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'usuario_id' => ['type' => 'integer'],
                            'inventario_hamaca_id' => ['type' => 'integer'],
                            'factura_id' => ['type' => 'integer', 'nullable' => true],
                            'ubicacion_origen_id' => ['type' => 'integer', 'nullable' => true],
                            'ubicacion_destino_id' => ['type' => 'integer', 'nullable' => true],
                            'tipo' => ['type' => 'string'],
                            'cantidad' => ['type' => 'integer'],
                            'fecha' => ['type' => 'string', 'format' => 'date'],
                        ],
                    ],
                    'Factura' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'pedido_id' => ['type' => 'integer', 'nullable' => true],
                            'pedido_numero' => ['type' => 'string', 'nullable' => true],
                            'origen' => ['type' => 'string', 'enum' => ['venta_directa', 'pedido']],
                            'cliente_id' => ['type' => 'integer', 'nullable' => true],
                            'vendedor_id' => ['type' => 'integer', 'nullable' => true],
                            'canal' => ['type' => 'string', 'example' => 'pos'],
                            'subtotal' => ['type' => 'number', 'format' => 'float'],
                            'iva' => ['type' => 'number', 'format' => 'float'],
                            'ir' => ['type' => 'number', 'format' => 'float'],
                            'total' => ['type' => 'number', 'format' => 'float'],
                            'servicios' => ['type' => 'array'],
                        ],
                    ],
                    'DetalleFactura' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'factura_id' => ['type' => 'integer'],
                            'hamaca_nombre' => ['type' => 'string'],
                            'inventario_hamaca_id' => ['type' => 'integer'],
                            'cantidad' => ['type' => 'integer'],
                            'precio_unitario' => ['type' => 'number', 'format' => 'float'],
                        ],
                    ],
                    'PosVentaRequest' => [
                        'type' => 'object',
                        'required' => ['items'],
                        'properties' => [
                            'cliente_id' => ['type' => 'integer', 'nullable' => true],
                            'vendedor_id' => ['type' => 'integer', 'nullable' => true],
                            'canal' => ['type' => 'string', 'enum' => ['pos', 'ecommerce'], 'example' => 'pos'],
                            'aplica_ir' => ['type' => 'boolean', 'example' => true],
                            'metodo_pago' => ['type' => 'string', 'nullable' => true, 'example' => 'efectivo'],
                            'descuento' => ['type' => 'number', 'format' => 'float', 'example' => 0],
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['inventario_hamaca_id', 'cantidad'],
                                    'properties' => [
                                        'inventario_hamaca_id' => ['type' => 'integer'],
                                        'cantidad' => ['type' => 'integer'],
                                        'precio_unitario' => ['type' => 'number', 'format' => 'float'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'PaginatedMeta' => [
                        'type' => 'object',
                        'properties' => [
                            'current_page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/v1/login' => [
                    'post' => [
                        'tags' => ['Auth'],
                        'summary' => 'Login administrativo',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/LoginRequest'],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Autenticacion exitosa',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/LoginResponse'],
                                    ],
                                ],
                            ],
                            '422' => ['description' => 'Credenciales invalidas'],
                        ],
                    ],
                ],
                '/v1/csrf-token' => [
                    'get' => ['tags' => ['Autenticacion'], 'summary' => 'Obtener token CSRF de la sesion web', 'responses' => ['200' => ['description' => 'Token CSRF']]],
                ],
                '/v1/me' => [
                    'get' => [
                        'tags' => ['Auth'],
                        'summary' => 'Usuario autenticado',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => [
                                'description' => 'Usuario actual',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/Usuario'],
                                    ],
                                ],
                            ],
                            '401' => ['description' => 'No autenticado'],
                        ],
                    ],
                ],
                '/v1/logout' => [
                    'post' => [
                        'tags' => ['Auth'],
                        'summary' => 'Cerrar sesion',
                        'security' => [['bearerAuth' => []]],
                        'responses' => [
                            '200' => [
                                'description' => 'Sesion cerrada',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                '/v1/categorias' => [
                    'get' => [
                        'tags' => ['Catalogos'],
                        'summary' => 'Listar categorias',
                        'responses' => ['200' => ['description' => 'Catalogo de categorias']],
                    ],
                    'post' => [
                        'tags' => ['Catalogos'],
                        'summary' => 'Crear categoria',
                        'security' => [['bearerAuth' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['nombre'],
                                        'properties' => [
                                            'nombre' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => ['201' => ['description' => 'Categoria creada']],
                    ],
                ],
                '/v1/categorias/{categoria}' => [
                    'get' => [
                        'tags' => ['Catalogos'],
                        'summary' => 'Ver categoria',
                        'parameters' => [$this->pathParameter('categoria')],
                        'responses' => ['200' => ['description' => 'Categoria']],
                    ],
                    'put' => [
                        'tags' => ['Catalogos'],
                        'summary' => 'Actualizar categoria',
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [$this->pathParameter('categoria')],
                        'responses' => ['200' => ['description' => 'Categoria actualizada']],
                    ],
                ],
                '/v1/tamanos' => [
                    'get' => ['tags' => ['Catalogos'], 'summary' => 'Listar tamanos', 'responses' => ['200' => ['description' => 'Catalogo de tamanos']]],
                    'post' => ['tags' => ['Catalogos'], 'summary' => 'Crear tamano', 'security' => [['bearerAuth' => []]], 'responses' => ['201' => ['description' => 'Tamano creado']]],
                ],
                '/v1/tamanos/{tamano}' => [
                    'get' => ['tags' => ['Catalogos'], 'summary' => 'Ver tamano', 'parameters' => [$this->pathParameter('tamano')], 'responses' => ['200' => ['description' => 'Tamano']]],
                    'put' => ['tags' => ['Catalogos'], 'summary' => 'Actualizar tamano', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('tamano')], 'responses' => ['200' => ['description' => 'Tamano actualizado']]],
                ],
                '/v1/ubicaciones' => [
                    'get' => ['tags' => ['Catalogos'], 'summary' => 'Listar ubicaciones', 'responses' => ['200' => ['description' => 'Catalogo de ubicaciones']]],
                    'post' => ['tags' => ['Catalogos'], 'summary' => 'Crear ubicacion', 'security' => [['bearerAuth' => []]], 'responses' => ['201' => ['description' => 'Ubicacion creada']]],
                ],
                '/v1/ubicaciones/{ubicacion}' => [
                    'get' => ['tags' => ['Catalogos'], 'summary' => 'Ver ubicacion', 'parameters' => [$this->pathParameter('ubicacion')], 'responses' => ['200' => ['description' => 'Ubicacion']]],
                    'put' => ['tags' => ['Catalogos'], 'summary' => 'Actualizar ubicacion', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('ubicacion')], 'responses' => ['200' => ['description' => 'Ubicacion actualizada']]],
                ],
                '/v1/colores' => [
                    'get' => ['tags' => ['Catalogos'], 'summary' => 'Listar colores', 'responses' => ['200' => ['description' => 'Catalogo de colores']]],
                    'post' => ['tags' => ['Catalogos'], 'summary' => 'Crear color', 'security' => [['bearerAuth' => []]], 'responses' => ['201' => ['description' => 'Color creado']]],
                ],
                '/v1/colores/{colore}' => [
                    'get' => ['tags' => ['Catalogos'], 'summary' => 'Ver color', 'parameters' => [$this->pathParameter('colore')], 'responses' => ['200' => ['description' => 'Color']]],
                    'put' => ['tags' => ['Catalogos'], 'summary' => 'Actualizar color', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('colore')], 'responses' => ['200' => ['description' => 'Color actualizado']]],
                ],
                '/v1/hamacas' => [
                    'get' => ['tags' => ['Hamacas'], 'summary' => 'Catalogo de hamacas', 'responses' => ['200' => ['description' => 'Listado de hamacas']]],
                    'post' => ['tags' => ['Hamacas'], 'summary' => 'Crear hamaca', 'security' => [['bearerAuth' => []]], 'responses' => ['201' => ['description' => 'Hamaca creada']]],
                ],
                '/v1/hamacas/detalles' => [
                    'get' => ['tags' => ['Hamacas'], 'summary' => 'Catalogo administrativo de hamacas con detalles', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Listado detallado']]],
                ],
                '/v1/hamacas/monthly-inventory' => [
                    'get' => ['tags' => ['Hamacas'], 'summary' => 'Inventario inicial mensual', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Total de inventario']]],
                ],
                '/v1/hamacas/{hamaca}' => [
                    'get' => ['tags' => ['Hamacas'], 'summary' => 'Ver hamaca', 'parameters' => [$this->pathParameter('hamaca')], 'responses' => ['200' => ['description' => 'Hamaca']]],
                    'put' => ['tags' => ['Hamacas'], 'summary' => 'Actualizar hamaca', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('hamaca')], 'responses' => ['200' => ['description' => 'Hamaca actualizada']]],
                ],
                '/v1/fotos' => [
                    'get' => ['tags' => ['Fotos'], 'summary' => 'Catalogo de fotos', 'responses' => ['200' => ['description' => 'Listado de fotos']]],
                    'post' => ['tags' => ['Fotos'], 'summary' => 'Crear foto', 'security' => [['bearerAuth' => []]], 'responses' => ['201' => ['description' => 'Foto creada']]],
                ],
                '/v1/fotos/{foto}' => [
                    'get' => ['tags' => ['Fotos'], 'summary' => 'Ver foto', 'parameters' => [$this->pathParameter('foto')], 'responses' => ['200' => ['description' => 'Foto']]],
                    'put' => ['tags' => ['Fotos'], 'summary' => 'Actualizar foto', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('foto')], 'responses' => ['200' => ['description' => 'Foto actualizada']]],
                    'delete' => ['tags' => ['Fotos'], 'summary' => 'Eliminar foto', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('foto')], 'responses' => ['200' => ['description' => 'Foto eliminada']]],
                ],
                '/v1/inventario-hamacas' => [
                    'get' => ['tags' => ['Inventario'], 'summary' => 'Listar inventario', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Inventario']]],
                    'post' => ['tags' => ['Inventario'], 'summary' => 'Crear registro de inventario', 'security' => [['bearerAuth' => []]], 'responses' => ['201' => ['description' => 'Inventario creado']]],
                ],
                '/v1/inventario-hamacas/{inventarioHamaca}' => [
                    'get' => ['tags' => ['Inventario'], 'summary' => 'Ver inventario', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('inventarioHamaca')], 'responses' => ['200' => ['description' => 'Inventario']]],
                    'put' => ['tags' => ['Inventario'], 'summary' => 'Actualizar inventario', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('inventarioHamaca')], 'responses' => ['200' => ['description' => 'Inventario actualizado']]],
                    'delete' => ['tags' => ['Inventario'], 'summary' => 'Eliminar inventario', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('inventarioHamaca')], 'responses' => ['200' => ['description' => 'Inventario eliminado']]],
                ],
                '/v1/inventario/entradas' => [
                    'post' => ['tags' => ['Inventario'], 'summary' => 'Registrar entrada atómica de inventario', 'security' => [['bearerAuth' => []]], 'responses' => ['201' => ['description' => 'Entrada registrada'], '409' => ['description' => 'Conflicto de negocio']]],
                ],
                '/v1/inventario/salidas' => [
                    'post' => ['tags' => ['Inventario'], 'summary' => 'Registrar salida atómica de inventario', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Salida registrada'], '409' => ['description' => 'Conflicto de negocio']]],
                ],
                '/v1/inventario/transferencias' => [
                    'post' => ['tags' => ['Inventario'], 'summary' => 'Registrar transferencia atómica de inventario', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Transferencia ejecutada'], '409' => ['description' => 'Conflicto de negocio']]],
                ],
                '/v1/usuarios' => [
                    'get' => ['tags' => ['Usuarios'], 'summary' => 'Listar usuarios', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Listado de usuarios']]],
                    'post' => ['tags' => ['Usuarios'], 'summary' => 'Crear usuario', 'security' => [['bearerAuth' => []]], 'responses' => ['201' => ['description' => 'Usuario creado']]],
                ],
                '/v1/usuarios/{usuario}' => [
                    'get' => ['tags' => ['Usuarios'], 'summary' => 'Ver usuario', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('usuario')], 'responses' => ['200' => ['description' => 'Usuario']]],
                    'put' => ['tags' => ['Usuarios'], 'summary' => 'Actualizar usuario', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('usuario')], 'responses' => ['200' => ['description' => 'Usuario actualizado']]],
                    'delete' => ['tags' => ['Usuarios'], 'summary' => 'Eliminar usuario', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('usuario')], 'responses' => ['200' => ['description' => 'Usuario eliminado']]],
                ],
                '/v1/pantallas' => [
                    'get' => ['tags' => ['Pantallas'], 'summary' => 'Listar pantallas', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'responses' => ['200' => ['description' => 'Listado de pantallas']]],
                    'post' => ['tags' => ['Pantallas'], 'summary' => 'Crear pantalla', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'responses' => ['201' => ['description' => 'Pantalla creada']]],
                ],
                '/v1/pantallas/{pantalla}' => [
                    'get' => ['tags' => ['Pantallas'], 'summary' => 'Ver pantalla', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'parameters' => [$this->pathParameter('pantalla')], 'responses' => ['200' => ['description' => 'Pantalla']]],
                    'put' => ['tags' => ['Pantallas'], 'summary' => 'Actualizar pantalla', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'parameters' => [$this->pathParameter('pantalla')], 'responses' => ['200' => ['description' => 'Pantalla actualizada']]],
                    'delete' => ['tags' => ['Pantallas'], 'summary' => 'Eliminar pantalla', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'parameters' => [$this->pathParameter('pantalla')], 'responses' => ['200' => ['description' => 'Pantalla eliminada']]],
                ],
                '/v1/permisos' => [
                    'get' => ['tags' => ['Permisos'], 'summary' => 'Listar permisos', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'responses' => ['200' => ['description' => 'Listado de permisos']]],
                    'post' => ['tags' => ['Permisos'], 'summary' => 'Crear permiso', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'responses' => ['201' => ['description' => 'Permiso creado']]],
                ],
                '/v1/permisos/{permiso}' => [
                    'get' => ['tags' => ['Permisos'], 'summary' => 'Ver permiso', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'parameters' => [$this->pathParameter('permiso')], 'responses' => ['200' => ['description' => 'Permiso']]],
                    'put' => ['tags' => ['Permisos'], 'summary' => 'Actualizar permiso', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'parameters' => [$this->pathParameter('permiso')], 'responses' => ['200' => ['description' => 'Permiso actualizado']]],
                    'delete' => ['tags' => ['Permisos'], 'summary' => 'Eliminar permiso', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'parameters' => [$this->pathParameter('permiso')], 'responses' => ['200' => ['description' => 'Permiso eliminado']]],
                ],
                '/v1/pantalla-permiso-roles' => [
                    'get' => ['tags' => ['Permisos'], 'summary' => 'Listar accesos por pantalla, permiso y rol', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'responses' => ['200' => ['description' => 'Listado de accesos']]],
                    'post' => ['tags' => ['Permisos'], 'summary' => 'Crear acceso por pantalla, permiso y rol', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'responses' => ['201' => ['description' => 'Acceso creado']]],
                ],
                '/v1/pantalla-permiso-roles/current' => [
                    'get' => ['tags' => ['Permisos'], 'summary' => 'Listar accesos del rol autenticado', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'responses' => ['200' => ['description' => 'Accesos del rol actual']]],
                ],
                '/v1/pantalla-permiso-roles/{pantalla_permiso_role}' => [
                    'get' => ['tags' => ['Permisos'], 'summary' => 'Ver acceso por rol', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'parameters' => [$this->pathParameter('pantalla_permiso_role')], 'responses' => ['200' => ['description' => 'Acceso']]],
                    'put' => ['tags' => ['Permisos'], 'summary' => 'Actualizar acceso por rol', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'parameters' => [$this->pathParameter('pantalla_permiso_role')], 'responses' => ['200' => ['description' => 'Acceso actualizado']]],
                    'delete' => ['tags' => ['Permisos'], 'summary' => 'Eliminar acceso por rol', 'security' => [['bearerAuth' => [], 'apiKeyAuth' => []]], 'parameters' => [$this->pathParameter('pantalla_permiso_role')], 'responses' => ['200' => ['description' => 'Acceso eliminado']]],
                ],
                '/v1/movimientos' => [
                    'get' => ['tags' => ['Movimientos'], 'summary' => 'Listar movimientos de auditoria', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Listado de movimientos']]],
                ],
                '/v1/movimientos/{movimiento}' => [
                    'get' => ['tags' => ['Movimientos'], 'summary' => 'Ver movimiento de auditoria', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('movimiento')], 'responses' => ['200' => ['description' => 'Movimiento']]],
                ],
                '/v1/movimientos/monthly-entries' => [
                    'get' => ['tags' => ['Movimientos'], 'summary' => 'Entradas mensuales', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Total de entradas']]],
                ],
                '/v1/movimientos/monthly-exits' => [
                    'get' => ['tags' => ['Movimientos'], 'summary' => 'Salidas mensuales', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Total de salidas']]],
                ],
                '/v1/dashboard/summary' => [
                    'get' => ['tags' => ['Dashboard'], 'summary' => 'Resumen real de inventario y movimientos', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Metricas del dashboard']]],
                ],
                '/v1/dashboard/movements-by-category' => [
                    'get' => ['tags' => ['Dashboard'], 'summary' => 'Movimientos por categoria', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Movimientos agrupados']]],
                ],
                '/v1/dashboard/categories/{categoriaId}/stats' => [
                    'get' => ['tags' => ['Dashboard'], 'summary' => 'Estadisticas de inventario por categoria', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('categoriaId')], 'responses' => ['200' => ['description' => 'Estadisticas de categoria']]],
                ],
                '/v1/facturas' => [
                    'get' => ['tags' => ['Facturas'], 'summary' => 'Listado de facturas', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Listado de facturas']]],
                ],
                '/v1/facturas/{factura}' => [
                    'get' => ['tags' => ['Facturas'], 'summary' => 'Ver factura', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('factura')], 'responses' => ['200' => ['description' => 'Factura']]],
                ],
                '/v1/facturas/{factura}/pdf' => [
                    'get' => ['tags' => ['Facturas'], 'summary' => 'Generar PDF de factura', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('factura'), ['name' => 'download', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],], 'responses' => ['200' => ['description' => 'PDF de factura', 'content' => ['application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']]]], '403' => ['description' => 'Acceso denegado']]],
                ],
                '/v1/proformas/{proforma}/pdf' => [
                    'get' => ['tags' => ['Proformas'], 'summary' => 'Generar PDF de proforma', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('proforma'), ['name' => 'download', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],], 'responses' => ['200' => ['description' => 'PDF de proforma', 'content' => ['application/pdf' => ['schema' => ['type' => 'string', 'format' => 'binary']]]], '403' => ['description' => 'Acceso denegado']]],
                ],
                '/v1/pedidos/{pedido}/facturar' => [
                    'post' => [
                        'tags' => ['Pedidos'],
                        'summary' => 'Facturar pedido terminado',
                        'security' => [['bearerAuth' => []]],
                        'parameters' => [$this->pathParameter('pedido')],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['canal', 'ubicacion_id', 'lineas'],
                                        'properties' => [
                                            'canal' => ['type' => 'string', 'enum' => ['pos', 'ecommerce']],
                                            'metodo_pago' => ['type' => 'string', 'nullable' => true],
                                            'ubicacion_id' => ['type' => 'integer'],
                                            'usuario_inventario_id' => ['type' => 'integer', 'nullable' => true],
                                            'lineas' => ['type' => 'array'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Factura existente devuelta por retry'],
                            '201' => ['description' => 'Factura creada'],
                            '403' => ['description' => 'Acceso denegado'],
                            '409' => ['description' => 'Pedido no facturable'],
                            '422' => ['description' => 'Datos físicos inválidos'],
                        ],
                    ],
                ],
                '/v1/detalle_facturas' => [
                    'get' => ['tags' => ['Facturas'], 'summary' => 'Listado de detalles de factura', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Listado de detalles']]],
                ],
                '/v1/detalle_facturas/{detalle_factura}' => [
                    'get' => ['tags' => ['Facturas'], 'summary' => 'Ver detalle de factura', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('detalle_factura')], 'responses' => ['200' => ['description' => 'Detalle']]],
                ],
                '/v1/pos/ventas' => [
                    'post' => [
                        'tags' => ['POS'],
                        'summary' => 'Registrar venta POS',
                        'security' => [['bearerAuth' => []]],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/PosVentaRequest'],
                                ],
                            ],
                        ],
                        'responses' => [
                            '201' => ['description' => 'Venta registrada'],
                            '422' => ['description' => 'Validacion fallida'],
                        ],
                    ],
                ],
                '/v1/hamacas/{hamaca}/recetas' => [
                    'get' => ['tags' => ['Producción'], 'summary' => 'Versiones de receta de una hamaca', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('hamaca')], 'responses' => ['200' => ['description' => 'Recetas']]],
                    'post' => ['tags' => ['Producción'], 'summary' => 'Crear borrador de receta para una hamaca', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('hamaca')], 'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['source_hamaca_id' => ['type' => 'integer']]]]]], 'responses' => ['201' => ['description' => 'Borrador creado'], '422' => ['description' => 'Hamaca origen inválida']]],
                ],
                '/v1/hamacas/{hamaca}/recetas/activa' => [
                    'get' => ['tags' => ['Producción'], 'summary' => 'Receta activa de una hamaca', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('hamaca')], 'responses' => ['200' => ['description' => 'Receta activa']]],
                ],
                '/v1/recetas-hamaca/{recetaHamaca}' => [
                    'get' => ['tags' => ['Producción'], 'summary' => 'Ver receta', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('recetaHamaca')], 'responses' => ['200' => ['description' => 'Receta']]],
                    'put' => ['tags' => ['Producción'], 'summary' => 'Guardar borrador de receta', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('recetaHamaca')], 'responses' => ['200' => ['description' => 'Receta actualizada'], '409' => ['description' => 'Receta no editable']]],
                ],
                '/v1/recetas-hamaca/{recetaHamaca}/costos' => [
                    'get' => ['tags' => ['Producción'], 'summary' => 'Costo estimado actual de receta', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('recetaHamaca')], 'responses' => ['200' => ['description' => 'Costos']]],
                ],
                '/v1/recetas-hamaca/{recetaHamaca}/activar' => [
                    'post' => ['tags' => ['Producción'], 'summary' => 'Activar receta borrador', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('recetaHamaca')], 'responses' => ['200' => ['description' => 'Receta activada'], '409' => ['description' => 'Regla de negocio']]],
                ],
                '/v1/recetas-hamaca/{recetaHamaca}/descartar' => [
                    'post' => ['tags' => ['Producción'], 'summary' => 'Descartar receta borrador', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('recetaHamaca')], 'responses' => ['200' => ['description' => 'Receta descartada'], '409' => ['description' => 'Regla de negocio']]],
                ],
                '/v1/servicios-adicionales/{servicioAdicional}/formula' => [
                    'get' => ['tags' => ['Producción'], 'summary' => 'Ver fórmula productiva del servicio', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('servicioAdicional')], 'responses' => ['200' => ['description' => 'Fórmula']]],
                    'put' => ['tags' => ['Producción'], 'summary' => 'Guardar fórmula productiva del servicio', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('servicioAdicional')], 'responses' => ['200' => ['description' => 'Fórmula actualizada']]],
                ],
                '/v1/servicios-adicionales/{servicioAdicional}/costos' => [
                    'get' => ['tags' => ['Producción'], 'summary' => 'Costo productivo del servicio', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('servicioAdicional')], 'responses' => ['200' => ['description' => 'Costos']]],
                ],
                '/v1/clientes' => [
                    'get' => ['tags' => ['Proformas'], 'summary' => 'Buscar clientes', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Clientes paginados']]],
                ],
                '/v1/proformas' => [
                    'get' => ['tags' => ['Proformas'], 'summary' => 'Listar proformas', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Proformas paginadas']]],
                    'post' => ['tags' => ['Proformas'], 'summary' => 'Crear borrador', 'security' => [['bearerAuth' => []]], 'responses' => ['201' => ['description' => 'Borrador creado']]],
                ],
                '/v1/proformas/calcular' => [
                    'post' => ['tags' => ['Proformas'], 'summary' => 'Calcular proforma sin persistir', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Cálculo']]],
                ],
                '/v1/proformas/{proforma}' => [
                    'get' => ['tags' => ['Proformas'], 'summary' => 'Ver proforma', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('proforma')], 'responses' => ['200' => ['description' => 'Proforma']]],
                    'put' => ['tags' => ['Proformas'], 'summary' => 'Actualizar borrador', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('proforma')], 'responses' => ['200' => ['description' => 'Proforma actualizada'], '409' => ['description' => 'Proforma no editable']]],
                ],
                '/v1/proformas/{proforma}/emitir' => [
                    'post' => ['tags' => ['Proformas'], 'summary' => 'Emitir proforma', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('proforma')], 'responses' => ['200' => ['description' => 'Proforma emitida']]],
                ],
                '/v1/proformas/{proforma}/pedido' => [
                    'post' => ['tags' => ['Pedidos'], 'summary' => 'Convertir proforma aceptada en pedido', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('proforma')], 'responses' => ['201' => ['description' => 'Pedido creado'], '409' => ['description' => 'Proforma no convertible']]],
                ],
                '/v1/pedidos' => [
                    'get' => ['tags' => ['Pedidos'], 'summary' => 'Listar pedidos', 'security' => [['bearerAuth' => []]], 'responses' => ['200' => ['description' => 'Pedidos paginados']]],
                ],
                '/v1/pedidos/{pedido}' => [
                    'get' => ['tags' => ['Pedidos'], 'summary' => 'Ver pedido', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('pedido')], 'responses' => ['200' => ['description' => 'Pedido']]],
                    'put' => ['tags' => ['Pedidos'], 'summary' => 'Actualizar logística', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('pedido')], 'responses' => ['200' => ['description' => 'Pedido actualizado']]],
                ],
                '/v1/pedidos/{pedido}/estado' => [
                    'post' => ['tags' => ['Pedidos'], 'summary' => 'Cambiar estado o cancelar', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('pedido')], 'responses' => ['200' => ['description' => 'Estado actualizado'], '409' => ['description' => 'Transición no válida']]],
                ],
                '/v1/pedidos/{pedido}/materiales/{pedidoMaterial}' => [
                    'put' => ['tags' => ['Pedidos'], 'summary' => 'Actualizar abastecimiento', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('pedido'), $this->pathParameter('pedidoMaterial')], 'responses' => ['200' => ['description' => 'Material actualizado']]],
                ],
                '/v1/pedidos/{pedido}/procesos/{pedidoProceso}' => [
                    'put' => ['tags' => ['Pedidos'], 'summary' => 'Actualizar proceso', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('pedido'), $this->pathParameter('pedidoProceso')], 'responses' => ['200' => ['description' => 'Proceso actualizado']]],
                ],
                '/v1/pedidos/{pedido}/historial' => [
                    'get' => ['tags' => ['Pedidos'], 'summary' => 'Historial del pedido', 'security' => [['bearerAuth' => []]], 'parameters' => [$this->pathParameter('pedido')], 'responses' => ['200' => ['description' => 'Historial']]],
                ],
                '/v1/documentation' => [
                    'get' => ['tags' => ['Documentacion'], 'summary' => 'Swagger UI', 'responses' => ['200' => ['description' => 'Interfaz Swagger']]],
                ],
                '/v1/openapi.json' => [
                    'get' => ['tags' => ['Documentacion'], 'summary' => 'OpenAPI JSON', 'responses' => ['200' => ['description' => 'Especificacion OpenAPI']]],
                ],
            ],
        ];
    }

    private function pathParameter(string $name): array
    {
        return [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'integer'],
        ];
    }
}
