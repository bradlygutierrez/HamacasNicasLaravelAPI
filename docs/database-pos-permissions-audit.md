# Auditoria de base de datos, POS, permisos y API key

Fecha: 2026-06-18

## Resumen

La base de datos del proyecto cumple razonablemente con tercera forma normal para el dominio actual. Las entidades principales estan separadas por responsabilidad, las relaciones muchos a muchos usan pivotes, y el inventario ya no duplica datos de catalogo. Hay desnormalizacion intencional en facturacion, porque una factura necesita conservar snapshots historicos aunque cambien clientes, hamacas, colores o precios despues de emitirse.

El proyecto queda preparado para un POS basico y operativo: puede autenticar vendedores, consultar inventario, registrar ventas transaccionales, generar facturas, crear detalles, descontar stock y registrar movimientos de salida. Tambien se corrigio la exposicion de `POST /api/v1/pos/ventas`, que ya tenia controlador, servicio y pruebas, pero no estaba registrado en `routes/api.php`.

## Revision 3FN

### Cumple 3FN

- `usuarios` mantiene datos propios del usuario administrativo y el rol operativo.
- `categorias`, `tamanos`, `ubicaciones` y `colores` son catalogos separados.
- `hamacas` depende de categoria y tamano por FK; no repite nombres de catalogos.
- `fotos` y `hamaca_foto` modelan correctamente la relacion muchos a muchos entre fotos y modelos de hamaca.
- `inventario_hamacas` representa existencias fisicas por modelo, propietario, ubicacion y composicion.
- `inventario_hamaca_color` evita guardar listas de colores como texto en inventario activo.
- `clientes` esta separado de `facturas`.
- `movimientos` referencia inventario, usuario, factura y ubicaciones por FK.
- `personal_access_tokens` queda a cargo de Sanctum.

### Desnormalizacion aceptada

- `facturas` guarda `nombre_cliente`, `ruc`, direccion, telefono, correo, impuestos y totales como snapshot fiscal.
- `detalle_facturas` guarda `hamaca_nombre`, descripcion, colores, precio y subtotal como snapshot historico.

Estos campos no rompen el diseno para facturacion; son necesarios para que una factura emitida no cambie si se actualiza el catalogo o el cliente.

### Observaciones futuras

- `usuarios.rol` y `pantalla_permiso_roles.rol` usan enum. Esto es consistente con el proyecto actual. Si los roles pasan a ser dinamicos, conviene crear tabla `roles` y migrar esas columnas a `role_id`.
- No hay tablas de caja, turnos, arqueos, devoluciones o pagos multiples. No son necesarias para el POS basico actual, pero si el negocio requiere control de caja completo deberian agregarse en otra fase.

## Preparacion para POS

El flujo POS actual queda cubierto por:

- `POST /api/v1/pos/ventas`: registra venta.
- `facturas`: encabezado fiscal de la venta.
- `detalle_facturas`: lineas historicas de la venta.
- `inventario_hamacas`: stock disponible por grupo fisico.
- `movimientos`: salida de inventario asociada a la factura.
- `VentaService`: ejecuta la operacion en transaccion y bloquea inventario con `lockForUpdate()`.

Limitaciones conocidas para un POS avanzado:

- No hay apertura/cierre de caja.
- No hay desglose de pagos multiples por factura.
- No hay devoluciones, anulaciones o notas de credito.
- No hay serie fiscal por sucursal/caja.
- No hay auditoria detallada de cambios de permisos.

## Nuevas tablas de permisos

### `pantallas`

Representa pantallas o modulos del frontend:

- `id`
- `nombre`
- `slug` unico
- `descripcion`
- `ruta`
- `icono`
- `orden`
- `state`
- timestamps

### `permisos`

Representa acciones reutilizables:

- `id`
- `nombre`
- `slug` unico
- `descripcion`
- timestamps

### `pantalla_permiso_roles`

Relaciona una pantalla, un permiso y un rol:

- `id`
- `pantalla_id`
- `permiso_id`
- `rol`: `admin`, `vendedor`, `almacenista`, `socio`
- timestamps

Tiene llave unica sobre `pantalla_id + permiso_id + rol` para evitar asignaciones duplicadas.

## Nuevos endpoints

Todos requieren Sanctum. Los endpoints administrativos tambien requieren rol `admin`.

- `GET /api/v1/pantallas`
- `POST /api/v1/pantallas`
- `GET /api/v1/pantallas/{pantalla}`
- `PUT/PATCH /api/v1/pantallas/{pantalla}`
- `DELETE /api/v1/pantallas/{pantalla}`
- `GET /api/v1/permisos`
- `POST /api/v1/permisos`
- `GET /api/v1/permisos/{permiso}`
- `PUT/PATCH /api/v1/permisos/{permiso}`
- `DELETE /api/v1/permisos/{permiso}`
- `GET /api/v1/pantalla-permiso-roles`
- `POST /api/v1/pantalla-permiso-roles`
- `GET /api/v1/pantalla-permiso-roles/current`
- `GET /api/v1/pantalla-permiso-roles/{pantalla_permiso_role}`
- `PUT/PATCH /api/v1/pantalla-permiso-roles/{pantalla_permiso_role}`
- `DELETE /api/v1/pantalla-permiso-roles/{pantalla_permiso_role}`

`GET /api/v1/pantalla-permiso-roles/current` devuelve los accesos del rol del usuario autenticado y sirve para construir navegacion o habilitar acciones en el frontend.

## API key con Sanctum

Se agrego el middleware `api.key` y se aplico junto a `auth:sanctum` en rutas protegidas.

Configuracion:

```env
HAMACAS_API_KEY=valor-secreto
```

Uso:

```http
Authorization: Bearer {sanctum_token}
X-API-Key: valor-secreto
```

Si `HAMACAS_API_KEY` esta vacio, el middleware no bloquea solicitudes. Esto permite desarrollo local sin API key. Si esta configurado, las rutas protegidas rechazan solicitudes sin `X-API-Key` correcto con `401`.

## Tasktree ejecutado

- Explorar estructura del proyecto, migraciones, modelos, rutas y configuracion de auth/API.
- Auditar esquema actual contra 3FN y necesidades de POS.
- Definir tablas `pantallas`, `permisos` y `pantalla_permiso_roles`.
- Agregar pruebas rojas para esquema, endpoints y API key.
- Crear migracion y aplicarla en MySQL local.
- Crear modelos, controllers, resources y collections para cada nuevo endpoint.
- Registrar rutas de permisos, POS y documentacion faltantes.
- Agregar middleware `api.key`.
- Actualizar OpenAPI y documentacion.
- Verificar con pruebas automatizadas.
