# Contrato API para Frontend - Hamacas Nica

Este documento fue generado leyendo el estado actual del proyecto Laravel en el workspace. La fuente de verdad para endpoints activos es `routes/api.php` confirmado con `php artisan route:list --path=api/v1`.

## Base

- Base URL local esperada: `/api`
- Version activa: `/v1`
- Content type JSON: `Content-Type: application/json`, salvo usuarios con foto, que debe enviarse como `multipart/form-data`.
- Autenticacion: Laravel Sanctum con header `Authorization: Bearer {access_token}`.
- API key: si el backend define `HAMACAS_API_KEY`, las rutas protegidas tambien requieren `X-API-Key: {valor}`.
- Roles usados por middleware: `admin`, `vendedor`, `almacenista`, `socio`.
- Error sin sesion: normalmente `401`.
- Error por rol insuficiente: `403` con `{ "message": "No autorizado." }`.
- Error de validacion Laravel: `422` con `{ "message": "...", "errors": { "campo": ["..."] } }`.
- Error de modelo no encontrado: `404`.
- Listados paginados aceptan `?page=N`. No hay `per_page` parametrizado en controladores.

## Esquemas reutilizables

Laravel `JsonResource` envuelve recursos individuales como `{ "data": { ... } }` cuando el controlador retorna `new XResource(...)`. Cuando el controlador usa `response()->json({ message, data: new XResource(...) })`, el campo `data` contiene directamente el objeto.

```json
{
  "Usuario": {
    "id": "number",
    "nombre": "string",
    "correo": "string",
    "foto": "string|null (URL asset storage)",
    "rol": "admin|vendedor|almacenista|socio",
    "state": "boolean",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "Categoria": {
    "id": "number",
    "nombre": "string",
    "descripcion": "string|null",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "Tamano": {
    "id": "number",
    "nombre": "string",
    "descripcion": "string|null",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "Ubicacion": {
    "id": "number",
    "nombre": "string",
    "descripcion": "string|null",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "Color": {
    "id": "number",
    "nombre": "string",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "HamacaResource": {
    "id": "number",
    "nombre": "string",
    "descripcion": "string|null",
    "categoria": "string|null",
    "tamano": "string|null",
    "precio": "number|string decimal",
    "colores": "array<{id:number,nombre:string}>|solo si relacion cargada",
    "fotos": "array<{id:number,ruta:string}>|solo si relacion cargada",
    "inventario": "array<{id:number,cantidad:number,ubicacion:{id,nombre}|null,usuario:{id,nombre}|null,colores:array}>|solo en /hamacas/detalles",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "Foto": {
    "id": "number",
    "ruta": "string",
    "hamacas": "array<HamacaResource>|si relacion cargada",
    "created_at": "YYYY-MM-DD HH:mm:ss|null",
    "updated_at": "YYYY-MM-DD HH:mm:ss|null"
  },
  "InventarioHamaca": {
    "id": "number",
    "cantidad": "number",
    "hamaca": "{id,nombre,descripcion,precio,categoria:{id,nombre}|null,tamano:{id,nombre}|null,fotos:array}|si relacion cargada",
    "ubicacion": "{id,nombre}|si relacion cargada",
    "usuario": "{id,nombre,rol}|si relacion cargada",
    "colores": "array<{id:number,nombre:string}>|si relacion cargada",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "Movimiento": {
    "id": "number",
    "usuario": "string|null (nombre del usuario)",
    "inventario_hamaca_id": "number",
    "tipo": "entrada|salida|transferencia",
    "cantidad": "number",
    "fecha": "datetime",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "Factura": {
    "id": "number",
    "numero": "string",
    "cliente": "{id,nombre,ruc,direccion}|null",
    "vendedor": "string|null",
    "canal": "pos|ecommerce",
    "nombre_cliente": "string",
    "ruc": "string|null",
    "direccion": "string|null",
    "telefono": "string|null",
    "correo": "string|null",
    "metodo_pago": "string|null",
    "subtotal": "number|string decimal",
    "descuento": "number|string decimal",
    "monto_iva": "number|string decimal",
    "monto_ir": "number|string decimal",
    "total": "number|string decimal",
    "fecha": "datetime",
    "detalles": "array<DetalleFactura>|si relacion cargada",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "DetalleFactura": {
    "id": "number",
    "factura_id": "number",
    "inventario_hamaca_id": "number",
    "hamaca": "string",
    "descripcion": "string|null",
    "cantidad": "number",
    "precio_unitario": "number|string decimal",
    "subtotal": "number|string decimal",
    "colores": "array<string>",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "Pantalla": {
    "id": "number",
    "nombre": "string",
    "slug": "string",
    "descripcion": "string|null",
    "ruta": "string|null",
    "icono": "string|null",
    "orden": "number",
    "state": "boolean",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "Permiso": {
    "id": "number",
    "nombre": "string",
    "slug": "string",
    "descripcion": "string|null",
    "created_at": "datetime",
    "updated_at": "datetime"
  },
  "PantallaPermisoRol": {
    "id": "number",
    "pantalla_id": "number",
    "permiso_id": "number",
    "rol": "admin|vendedor|almacenista|socio",
    "pantalla": "Pantalla|null",
    "permiso": "Permiso|null",
    "created_at": "datetime",
    "updated_at": "datetime"
  }
}
```

## Endpoints activos

### Auth

#### `POST /api/v1/login`

- Auth: publica.
- Body:

```json
{
  "correo": "admin@example.com",
  "password": "secret123"
}
```

- Validacion: `correo` requerido email, `password` requerido string.
- Respuesta `200`:

```json
{
  "message": "Login exitoso",
  "access_token": "token sanctum",
  "token_type": "Bearer",
  "user": "Usuario"
}
```

- Errores propios: `401 { "message": "Credenciales inválidas" }`, `403 { "message": "Usuario inactivo" }`.

#### `GET /api/v1/me`

- Auth: cualquier usuario autenticado.
- Body: ninguno.
- Respuesta `200`: `{ "data": "Usuario" }`.

#### `POST /api/v1/logout`

- Auth: cualquier usuario autenticado.
- Body: ninguno.
- Respuesta `200`: `{ "message": "Sesión cerrada correctamente" }`.

### Categorias

#### `GET /api/v1/categorias`

- Auth: publica.
- Query: `page`.
- Respuesta `200`:

```json
{
  "data": ["Categoria"],
  "meta": {
    "total": "number",
    "per_page": "number",
    "current_page": "number",
    "last_page": "number"
  }
}
```

#### `GET /api/v1/categorias/{categoria}`

- Auth: publica.
- Path: `categoria` id number.
- Respuesta `200`: `{ "data": "Categoria" }`.

#### `POST /api/v1/categorias`

- Auth: `admin`.
- Body:

```json
{
  "nombre": "Familiar",
  "descripcion": "Opcional"
}
```

- Validacion: `nombre` requerido string max 100, `descripcion` nullable string.
- Respuesta: `{ "data": "Categoria" }`. El controlador no fuerza status `201`, por defecto es `200`.

#### `PUT /api/v1/categorias/{categoria}`

- Auth: `admin`.
- Body parcial:

```json
{
  "nombre": "Nueva categoria",
  "descripcion": "Opcional"
}
```

- Validacion: `nombre` sometimes string max 100, `descripcion` nullable string.
- Respuesta `200`: `{ "data": "Categoria" }`.

### Tamanos

#### `GET /api/v1/tamanos`

- Auth: publica.
- Query: `page`.
- Respuesta `200`:

```json
{
  "data": ["Tamano"],
  "organization": "Hamacas Nica",
  "author": "Bradly Gutierrez",
  "type": "Collection of Sizes"
}
```

#### `GET /api/v1/tamanos/{tamano}`

- Auth: publica.
- Respuesta `200`: `{ "data": "Tamano" }`.

#### `POST /api/v1/tamanos`

- Auth: `admin`.
- Body:

```json
{
  "nombre": "Grande",
  "descripcion": "Opcional"
}
```

- Validacion: `nombre` requerido string max 255, `descripcion` nullable string.
- Respuesta `201`:

```json
{
  "message": "Tamano creado exitosamente",
  "data": "Tamano"
}
```

#### `PUT /api/v1/tamanos/{tamano}`

- Auth: `admin`.
- Body parcial:

```json
{
  "nombre": "Mediano",
  "descripcion": "Opcional"
}
```

- Validacion: `nombre` sometimes required string max 255, `descripcion` sometimes nullable string.
- Respuesta `200`: `{ "message": "Tamano actualizado exitosamente", "data": "Tamano" }`.

### Ubicaciones

#### `GET /api/v1/ubicaciones`

- Auth: publica.
- Query: `page`.
- Respuesta `200`:

```json
{
  "data": ["Ubicacion"],
  "organization": "Hamacas Nica",
  "author": "Bradly Gutierrez",
  "type": "Ubicacion Collection Resource"
}
```

#### `GET /api/v1/ubicaciones/{ubicacion}`

- Auth: publica.
- Respuesta `200`: `{ "data": "Ubicacion" }`.

#### `POST /api/v1/ubicaciones`

- Auth: `admin`.
- Body: `{ "nombre": "Mercado", "descripcion": "Opcional" }`.
- Validacion: `nombre` requerido string max 255, `descripcion` nullable string.
- Respuesta `201`: `{ "message": "Ubicación creada exitosamente", "data": "Ubicacion" }`.

#### `PUT /api/v1/ubicaciones/{ubicacion}`

- Auth: `admin`.
- Body parcial: `{ "nombre": "Bodega", "descripcion": "Opcional" }`.
- Validacion: `nombre` sometimes required string max 255, `descripcion` sometimes nullable string.
- Respuesta `200`: `{ "message": "Ubicación actualizada exitosamente", "data": "Ubicacion" }`.

### Colores

#### `GET /api/v1/colores`

- Auth: publica.
- Query: `page`.
- Respuesta `200`:

```json
{
  "data": ["Color"],
  "meta": {
    "total": "number",
    "per_page": "number",
    "current_page": "number",
    "last_page": "number"
  }
}
```

#### `GET /api/v1/colores/{colore}`

- Auth: publica.
- Nota: el parametro se llama `{colore}` por route model binding, pero es el id de color.
- Respuesta `200`: `{ "data": "Color" }`.

#### `POST /api/v1/colores`

- Auth: `admin`.
- Body: `{ "nombre": "Azul" }`.
- Validacion: `nombre` requerido string max 100.
- Respuesta `201`: `{ "message": "Color creada correctamente", "data": "Color" }`.

#### `PUT /api/v1/colores/{colore}`

- Auth: `admin`.
- Body parcial: `{ "nombre": "Rojo" }`.
- Validacion: `nombre` sometimes string max 100.
- Respuesta actual `201`: `{ "message": "Color creada correctamente", "data": "Color" }`. Nota: aunque es update, el mensaje/status dicen creada.

### Hamacas

#### `GET /api/v1/hamacas`

- Auth: publica.
- Query: `page`.
- Respuesta `200`:

```json
{
  "data": [
    {
      "id": "number",
      "nombre": "string",
      "descripcion": "string|null",
      "categoria_id": "number",
      "tamano_id": "number",
      "precio": "number|string decimal",
      "created_at": "datetime",
      "updated_at": "datetime"
    }
  ],
  "meta": {
    "Organization": "Hamacas Nica",
    "author": "Bradly Gutierrez"
  },
  "type": "Hamacas Collection"
}
```

#### `GET /api/v1/hamacas/detalles`

- Auth: publica.
- Query: `page`.
- Uso frontend: catalogo enriquecido para pantalla de productos/inventario.
- Respuesta `200`: misma envoltura de `HamacaCollection`, pero cada item trae relaciones Eloquent cargadas: `categoria`, `tamano`, `fotos`, `inventarios`, y dentro de inventarios: `colores`, `usuario`, `ubicacion`.

#### `GET /api/v1/hamacas/monthly-inventory`

- Auth: publica.
- Respuesta `200`: `{ "total": "number" }`.
- Calcula suma de `inventario_hamacas.cantidad`.

#### `GET /api/v1/hamacas/{hamaca}`

- Auth: publica.
- Respuesta `200`: `{ "data": "HamacaResource" }`, con `categoria`, `tamano`, `colores`, `fotos`.
- Nota tecnica: el controlador intenta cargar relacion `colores` en Hamaca, pero el modelo actual `Hamaca` no define `colores()`. Este endpoint puede fallar hasta que se corrija esa relacion.

#### `POST /api/v1/hamacas`

- Auth: `admin`.
- Body:

```json
{
  "nombre": "Hamaca familiar",
  "descripcion": "Opcional",
  "categoria_id": 1,
  "tamano_id": 1,
  "precio": 1500.0
}
```

- Validacion: `nombre` requerido string max 100, `descripcion` nullable string, `categoria_id` requerido integer, `tamano_id` requerido integer, `precio` requerido numeric.
- Respuesta `201`: `{ "message": "Hamaca creada correctamente", "data": "HamacaResource" }`.

#### `PUT /api/v1/hamacas/{hamaca}`

- Auth: `admin`.
- Body parcial:

```json
{
  "nombre": "Hamaca actualizada",
  "descripcion": "Opcional",
  "categoria_id": 1,
  "tamano_id": 1,
  "precio": 1750.0
}
```

- Validacion: campos `sometimes`; `descripcion` nullable.
- Respuesta actual `201`: `{ "message": "Hamaca actualizada correctamente", "data": "HamacaResource" }`.

### Fotos

#### `GET /api/v1/fotos`

- Auth: publica.
- Query: `page`.
- Respuesta `200`:

```json
{
  "data": ["Foto"],
  "meta": {
    "current_page": "number",
    "last_page": "number",
    "per_page": "number",
    "total": "number"
  },
  "links": {
    "first": "string|null",
    "last": "string|null",
    "prev": "string|null",
    "next": "string|null"
  }
}
```

#### `GET /api/v1/fotos/{foto}`

- Auth: publica.
- Respuesta `200`: `{ "data": "Foto" }`, incluye `hamacas`.

#### `POST /api/v1/fotos`

- Auth: `admin`.
- Body:

```json
{
  "ruta": "fotos/hamaca.jpg",
  "hamaca_ids": [1, 2]
}
```

- Validacion: `ruta` requerida string max 255, `hamaca_ids` requerido array min 1, cada id integer existente en `hamacas`.
- Respuesta `201`: `{ "message": "Foto creada correctamente.", "data": "Foto" }`.

#### `PUT /api/v1/fotos/{foto}`

- Auth: `admin`.
- Body parcial:

```json
{
  "ruta": "fotos/nueva.jpg",
  "hamaca_ids": [1, 3]
}
```

- Validacion: `ruta` sometimes string max 255, `hamaca_ids` sometimes array min 1, ids existentes.
- Respuesta `200`: `{ "message": "Foto actualizada correctamente.", "data": "Foto" }`.

#### `DELETE /api/v1/fotos/{foto}`

- Auth: `admin`.
- Respuesta `200`: `{ "message": "Foto eliminada correctamente." }`.

### Inventario de hamacas

#### `GET /api/v1/inventario-hamacas`

- Auth: cualquier usuario autenticado.
- Query: `page`.
- Respuesta `200`:

```json
{
  "data": ["InventarioHamaca"],
  "meta": {
    "total": "number",
    "count": "number",
    "per_page": "number",
    "current_page": "number",
    "last_page": "number"
  },
  "links": {
    "first": "string|null",
    "last": "string|null",
    "prev": "string|null",
    "next": "string|null"
  }
}
```

#### `GET /api/v1/inventario-hamacas/{inventarioHamaca}`

- Auth: cualquier usuario autenticado.
- Respuesta `200`: `{ "data": "InventarioHamaca" }`.

#### `POST /api/v1/inventario-hamacas`

- Auth: `almacenista` o `admin`.
- Body:

```json
{
  "hamaca_id": 1,
  "usuario_id": 2,
  "ubicacion_id": 1,
  "color_ids": [1, 2, 3, 4],
  "cantidad": 5
}
```

- Validacion: `hamaca_id`, `usuario_id`, `ubicacion_id` requeridos integer existentes; `color_ids` requerido array min 1 con ids existentes; `cantidad` requerida integer min 1.
- Comportamiento: si ya existe inventario con misma hamaca, usuario, ubicacion y composicion de colores, suma cantidad; si no existe, crea.
- Respuesta `201`: `{ "message": "Inventario creado correctamente", "data": "InventarioHamaca" }`.

#### `PUT /api/v1/inventario-hamacas/{inventarioHamaca}`

- Auth: `almacenista` o `admin`.
- Body completo, no parcial:

```json
{
  "hamaca_id": 1,
  "usuario_id": 2,
  "ubicacion_id": 1,
  "color_ids": [1, 2],
  "cantidad": 8
}
```

- Validacion: igual que `POST`.
- Respuesta `200`: `{ "message": "Inventario actualizado correctamente", "data": "InventarioHamaca" }`.

#### `DELETE /api/v1/inventario-hamacas/{inventarioHamaca}`

- Auth: `admin`.
- Respuesta `200`: `{ "message": "Inventario eliminado correctamente." }`.

#### `POST /api/v1/inventario-hamacas/transfer`

- Auth: `almacenista` o `admin`.
- Body:

```json
{
  "inventario_hamaca_id": 1,
  "cantidad": 2,
  "ubicacion_destino_id": 3
}
```

- Validacion: `inventario_hamaca_id` requerido integer existente, `cantidad` requerida integer min 1, `ubicacion_destino_id` nullable integer existente.
- Comportamiento actual: descuenta cantidad del registro origen; si `ubicacion_destino_id` viene, cambia la ubicacion del mismo registro. No crea un segundo registro destino.
- Error posible: si stock insuficiente lanza excepcion y puede responder `500`.
- Respuesta `200`: `{ "message": "Transferencia realizada correctamente.", "data": "InventarioHamaca" }`.

### Usuarios administrativos

#### `GET /api/v1/usuarios`

- Auth: `admin`.
- Query: `page`. Paginacion fija de 10.
- Respuesta `200`:

```json
{
  "data": ["Usuario"],
  "meta": {
    "Organization": "Hamacas Nica",
    "author": "Bradly Gutierrez"
  },
  "type": "Usuarios Collection",
  "message": "No hay usuarios activos disponibles.|null"
}
```

- Solo lista usuarios con `state = true`.

#### `GET /api/v1/usuarios/{usuario}`

- Auth: `admin`.
- Respuesta `200`: `{ "data": "Usuario" }`.

#### `POST /api/v1/usuarios`

- Auth: `admin`.
- Content type recomendado: `multipart/form-data` si incluye foto; JSON si no incluye foto.
- Body:

```json
{
  "nombre": "Vendedor",
  "correo": "vendedor@example.com",
  "password": "secret123",
  "foto": "file image opcional max 2048KB",
  "rol": "admin|vendedor|almacenista|socio"
}
```

- Validacion: `nombre` requerido string max 100, `correo` requerido email max 150 unico, `password` requerido string min 6, `foto` nullable image max 2048KB, `rol` nullable enum.
- Respuesta `201`: `{ "message": "Usuario creado correctamente", "data": "Usuario" }`.

#### `PUT /api/v1/usuarios/{usuario}`

- Auth: `admin`.
- Body parcial:

```json
{
  "nombre": "Nombre",
  "correo": "nuevo@example.com",
  "password": "secret123",
  "foto": "file image opcional max 2048KB",
  "rol": "admin|vendedor|almacenista|socio"
}
```

- Validacion: campos `sometimes`; `correo` unico ignorando el usuario actual; `foto` nullable image max 2048KB.
- Respuesta `200`: `{ "message": "Usuario actualizado correctamente", "data": "Usuario" }`.

#### `DELETE /api/v1/usuarios/{usuario}`

- Auth: `admin`.
- Comportamiento: no borra el registro; cambia `state` a `false` y elimina archivo de foto si existe.
- Respuesta `200`: `{ "message": "Usuario eliminado correctamente." }`.

### Movimientos

#### `GET /api/v1/movimientos/monthly-entries`

- Auth: cualquier usuario autenticado.
- Respuesta `200`: `{ "entries": "number" }`.
- Calcula suma del mes actual donde `tipo = entrada`.

#### `GET /api/v1/movimientos/monthly-exits`

- Auth: cualquier usuario autenticado.
- Respuesta `200`: `{ "exits": "number" }`.
- Calcula suma del mes actual donde `tipo = salida`.

#### `GET /api/v1/movimientos`

- Auth: cualquier usuario autenticado.
- Query: `page`.
- Respuesta `200`: `{ "data": ["Movimiento"] }`.

#### `POST /api/v1/movimientos`

- Auth: `almacenista` o `admin`.
- Body requerido: `inventario_hamaca_id`, `usuario_id`, `tipo` (`entrada`, `salida`, `transferencia`), `cantidad`.
- Body opcional: `factura_id`, `ubicacion_origen_id`, `ubicacion_destino_id`, `fecha`.
- Respuesta `201`: `{ "message": "Movimiento creado correctamente", "data": "Movimiento" }`.

#### `GET /api/v1/movimientos/{movimiento}`

- Auth: cualquier usuario autenticado.
- Respuesta `200`: `{ "data": "Movimiento" }`.

#### `PUT /api/v1/movimientos/{movimiento}`

- Auth: `almacenista` o `admin`.
- Body parcial: `inventario_hamaca_id`, `usuario_id`, `factura_id`, `ubicacion_origen_id`, `ubicacion_destino_id`, `tipo`, `cantidad`, `fecha`.
- Respuesta `200`: `{ "message": "Movimiento actualizado correctamente", "data": "Movimiento" }`.

#### `DELETE /api/v1/movimientos/{movimiento}`

- Auth: `admin`.
- Respuesta `200`: `{ "message": "Movimiento eliminado correctamente" }`.

### Facturas

#### `GET /api/v1/facturas`

- Auth: cualquier usuario autenticado.
- Query: `page`.
- Respuesta `200`:

```json
{
  "data": ["Factura"],
  "meta": {
    "Organization": "Hamacas Nica",
    "author": "Bradly Gutierrez"
  },
  "type": "Facturas Collection"
}
```

#### `GET /api/v1/facturas/{factura}`

- Auth: cualquier usuario autenticado.
- Respuesta `200`: `{ "data": "Factura" }`, incluye `cliente`, `usuario/vendedor` y `detalles`.

### Detalle de facturas

#### `GET /api/v1/detalle_facturas`

- Auth: cualquier usuario autenticado.
- Query: `page`.
- Respuesta `200`:

```json
{
  "data": ["DetalleFactura"],
  "meta": {
    "total": "number (count de la pagina actual)",
    "organization": "Hamacas Nica",
    "author": "Bradly Gutierrez"
  },
  "type": "Detalle Factura Collection"
}
```

#### `GET /api/v1/detalle_facturas/{detalle_factura}`

- Auth: cualquier usuario autenticado.
- Path: por convencion de `apiResource`, el parametro es `{detalle_factura}`.
- Respuesta `200`: `{ "data": "DetalleFactura" }`.

## Endpoints POS y permisos agregados

### POS activo: `POST /api/v1/pos/ventas`

- Estado: activo en `routes/api.php`.
- Auth: `vendedor` o `admin`, con Sanctum y API key si `HAMACAS_API_KEY` esta configurada.
- Body esperado:

```json
{
  "cliente_id": 1,
  "nombre_cliente": "Consumidor final",
  "ruc": "string|null",
  "direccion": "string|null",
  "telefono": "string|null",
  "correo": "cliente@example.com|null",
  "metodo_pago": "efectivo",
  "canal": "pos|ecommerce",
  "descuento": 0,
  "aplica_ir": false,
  "items": [
    {
      "inventario_hamaca_id": 1,
      "cantidad": 2
    }
  ]
}
```

- Validacion: `nombre_cliente` y `canal` requeridos; `items` requerido array min 1; cada item requiere `inventario_hamaca_id` existente y `cantidad` min 1.
- Respuesta: `201 { "message": "Venta registrada correctamente.", "data": "Factura" }`.
- Calculos: subtotal desde precio de hamaca del inventario, descuento, IVA 15%, IR 2% si `aplica_ir`, total = base + IVA - IR. Reduce stock y crea movimiento `salida`.

### Pantallas y permisos

#### `GET /api/v1/pantallas`

- Auth: `admin`.
- Respuesta: coleccion de `Pantalla`.

#### `POST /api/v1/pantallas`

- Auth: `admin`.
- Body:

```json
{
  "nombre": "Ventas POS",
  "slug": "ventas-pos",
  "descripcion": "Opcional",
  "ruta": "/ventas",
  "icono": "shopping-cart",
  "orden": 30,
  "state": true
}
```

#### `GET /api/v1/permisos`

- Auth: `admin`.
- Respuesta: coleccion de `Permiso`.

#### `POST /api/v1/permisos`

- Auth: `admin`.
- Body:

```json
{
  "nombre": "Ver",
  "slug": "ver",
  "descripcion": "Permite consultar una pantalla"
}
```

#### `GET /api/v1/pantalla-permiso-roles`

- Auth: `admin`.
- Query opcional: `rol`, `pantalla_id`, `permiso_id`.
- Respuesta: coleccion de `PantallaPermisoRol`.

#### `POST /api/v1/pantalla-permiso-roles`

- Auth: `admin`.
- Body:

```json
{
  "pantalla_id": 1,
  "permiso_id": 1,
  "rol": "vendedor"
}
```

#### `GET /api/v1/pantalla-permiso-roles/current`

- Auth: cualquier usuario autenticado.
- Respuesta: accesos del rol del usuario actual.

### Documentacion activa

- `GET /api/v1/documentation`: Swagger UI.
- `GET /api/v1/openapi.json`: especificacion OpenAPI.

## Controladores/metodos existentes pero sin endpoint activo

### Dashboard planeado

- `DashboardController@movementsByCategory`: no activo. Respuesta implementada: `{ "data": [{ "categoria": "string", "entradas": "number", "salidas": "number" }] }`.
- `DashboardController@categoryStats($categoriaId)`: no activo. Respuesta implementada: `{ "stock_minimo": "number|null", "stock_maximo": "number|null", "total_productos": "number" }`.
- Nota tecnica: este metodo consulta `cantidad` en `hamacas`, pero la tabla `hamacas` actual no tiene columna `cantidad`; puede fallar aunque se registre ruta.

### CRUD no expuesto por rutas actuales

Los controladores tienen metodos que no estan expuestos:

- `CategoriaController@destroy`: no hay `DELETE /categorias/{categoria}` activo.
- `TamanoController@destroy`: no hay `DELETE /tamanos/{tamano}` activo.
- `UbicacionController@destroy`: no hay `DELETE /ubicaciones/{ubicacion}` activo.
- `ColorController@destroy`: no hay `DELETE /colores/{colore}` activo.
- `HamacaController@destroy`: no hay `DELETE /hamacas/{hamaca}` activo.
- `DetalleFacturaController@store/update/destroy`: no hay `POST/PUT/DELETE /detalle_facturas` activo.
- `FacturaController` solo tiene `index/show`.
- No hay endpoints activos para `clientes`.

## Pantallas sugeridas para pedir al frontend

Con los endpoints activos actuales, las pantallas viables son:

1. Login y sesion actual.
2. Dashboard basico con tarjetas: inventario total, entradas mensuales, salidas mensuales.
3. Catalogos CRUD admin: categorias, tamanos, ubicaciones, colores.
4. Productos/hamacas: listado publico, detalle, crear/editar admin.
5. Fotos: listado, asociar una foto a una o varias hamacas, editar, eliminar.
6. Inventario: listado protegido, crear/sumar stock, editar composicion/cantidad, transferir, eliminar admin.
7. Usuarios admin: listado activos, crear, editar, desactivar.
8. Movimientos: listado y detalle de auditoria.
9. Facturas y detalle de facturas: solo lectura.

La pantalla POS puede usar `POST /api/v1/pos/ventas` para registrar ventas y las rutas de facturas para consultar comprobantes emitidos.
