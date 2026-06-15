# Refactor de inventario, POS, facturacion y autenticacion

Fecha: 2026-06-15

## Objetivo

Reconstruir la base de prueba y completar una API Laravel 12 reproducible para:

- catalogar modelos de hamacas, fotos y colores;
- controlar existencias por modelo, socio, ubicacion y composicion de colores;
- registrar ventas POS y generar facturas imprimibles;
- autenticar al personal administrativo con Laravel Sanctum;
- dejar preparada la base para clientes y comercio electronico, sin implementar pagos ni autenticacion de clientes en esta fase.

La solucion se mantendra como un monolito modular. Catalogo, inventario, ventas y autenticacion compartiran una API y una base de datos, pero tendran responsabilidades y rutas separadas.

## Alcance

Incluye:

- migrations completas para reconstruir el esquema de dominio;
- eliminacion del modelo y tabla `users`;
- modelo autenticable unico `Usuario`;
- login, logout y consulta del usuario autenticado con Sanctum;
- catalogo publico de consulta;
- administracion protegida por roles;
- inventario multicolor con propietario obligatorio;
- endpoint transaccional de venta POS;
- facturas con IVA e IR configurables;
- documentacion OpenAPI interactiva con Swagger UI;
- datos de prueba coherentes.

No incluye:

- pasarelas o procesamiento de pagos;
- carrito, checkout o pedidos e-commerce;
- registro, login o recuperacion de contrasena de clientes;
- control de existencias de hilo por color;
- serializacion individual de cada hamaca fisica.

## Modelo de dominio

### Hamacas

`hamacas` representa exclusivamente el modelo base:

- `id` INT;
- `nombre`;
- `descripcion`;
- `categoria_id`;
- `tamano_id`;
- `precio`;
- timestamps.

No contiene propietario, ubicacion, cantidad ni colores fisicos.

### Fotos

`fotos` almacena recursos reutilizables y `hamaca_foto` mantiene la relacion muchos a muchos con los modelos de hamaca.

La pivote usa clave primaria compuesta:

- `hamaca_id`;
- `foto_id`.

Las foreign keys usan tipos compatibles con las claves INT de las tablas de dominio y eliminacion en cascada.

### Colores

`colores` es un catalogo descriptivo. No controla existencias de hilo.

La composicion real pertenece al grupo de inventario mediante `inventario_hamaca_color`:

- `inventario_hamaca_id`;
- `color_id`;
- clave primaria compuesta.

La tabla `hamaca_color` se elimina para evitar dos fuentes contradictorias sobre los colores de una existencia.

### Inventario

`inventario_hamacas` representa un grupo de unidades fisicas equivalentes:

- `id` INT;
- `hamaca_id` obligatorio;
- `usuario_id` obligatorio, propietario o socio;
- `ubicacion_id` obligatorio;
- `cantidad` positiva;
- `composicion_clave`, calculada a partir de los IDs de colores ordenados;
- timestamps.

La combinacion siguiente es unica:

`hamaca_id + usuario_id + ubicacion_id + composicion_clave`

Esto permite:

- el mismo modelo y color para Bradly y Jacksa en la misma ubicacion;
- varios grupos del mismo modelo y socio cuando tienen composiciones diferentes;
- agrupar dos o mas unidades realmente equivalentes bajo una cantidad;
- vender una unidad por vez descontando la cantidad del grupo.

Toda creacion o incremento de inventario normaliza los colores, calcula la clave y reutiliza el grupo equivalente. La operacion se ejecuta en una transaccion para evitar duplicados por concurrencia.

`usuario_hamaca` se elimina. La propiedad y la cantidad viven exclusivamente en `inventario_hamacas`.

### Movimientos

`movimientos` referencia `inventario_hamaca_id`, no solamente `hamaca_id`. Registra:

- tipo `entrada`, `salida` o `transferencia`;
- cantidad;
- usuario administrativo que ejecuto la operacion;
- ubicaciones de origen y destino cuando correspondan;
- factura asociada cuando la salida proviene de una venta;
- fecha y timestamps.

Una transferencia conserva el socio propietario y la composicion de colores. El destino se combina con un grupo equivalente si ya existe.

## Clientes

`clientes` queda preparada para POS y futuro e-commerce:

- `id` INT;
- `nombre`;
- `ruc` nullable;
- `direccion` nullable;
- `telefono` nullable;
- `correo` nullable y unico cuando exista;
- `password` nullable para habilitar autenticacion en una fase futura;
- `state`;
- timestamps.

No usa Sanctum ni endpoints de autenticacion en esta fase.

Una venta POS puede usar un `cliente_id` existente o guardar datos de consumidor final sin crear una cuenta.

## Facturacion y POS

### Facturas

`facturas` conserva una copia historica de la informacion fiscal, aunque exista un cliente relacionado:

- `id` INT;
- `numero` unico;
- `cliente_id` nullable;
- `vendedor_id` obligatorio para POS;
- `canal`: `pos` o `ecommerce`;
- `nombre_cliente`;
- `ruc` nullable;
- `direccion` nullable;
- `telefono` nullable;
- `correo` nullable;
- `metodo_pago` informativo y nullable;
- `subtotal`;
- `descuento`;
- `tasa_iva`;
- `monto_iva`;
- `aplica_ir`;
- `tasa_ir`;
- `monto_ir`;
- `total`;
- `fecha`;
- timestamps.

Valores iniciales:

- IVA: 15%;
- IR: 2%, opcional por factura;
- base imponible: `subtotal - descuento`;
- monto IVA: `base imponible * tasa_iva`;
- monto IR: `base imponible * tasa_ir` cuando `aplica_ir` es verdadero;
- formula: `total = subtotal - descuento + monto_iva - monto_ir`.

La API no inferira el IR desde el metodo de pago. La solicitud indicara si aplica y la factura guardara la tasa y el monto utilizados.

### Detalles

`detalle_facturas` referencia `inventario_hamaca_id` y conserva una copia historica:

- modelo y descripcion;
- socio propietario;
- ubicacion;
- composicion de colores;
- cantidad;
- precio unitario;
- subtotal de linea.

Los cambios futuros al catalogo no alteran facturas emitidas.

### Endpoint de venta

`POST /api/v1/pos/ventas` recibe datos del comprador, configuracion fiscal y lineas con `inventario_hamaca_id` y cantidad. El precio unitario se obtiene en el servidor desde el modelo de hamaca relacionado con el inventario.

La operacion:

1. inicia una transaccion;
2. bloquea las filas de inventario con `SELECT ... FOR UPDATE`;
3. valida que todas las cantidades sean positivas y haya stock;
4. calcula subtotal, descuento, IVA, IR y total en el servidor;
5. crea factura y detalles;
6. descuenta inventario;
7. crea movimientos de salida;
8. confirma la transaccion;
9. devuelve la factura completa lista para impresion.

Ante cualquier error se revierte toda la operacion. El servidor no acepta precios, impuestos ni totales calculados por el cliente como fuente de verdad.

`GET /api/v1/facturas/{factura}` devuelve la representacion imprimible. La impresion fisica corresponde al frontend POS.

## Autenticacion y autorizacion

### Modelo unico

`Usuario` extiende `Authenticatable`, usa `HasApiTokens` y apunta a `usuarios`.

Cambios:

- `usuarios.contraseña` se renombra a `password`;
- `correo` sigue siendo el identificador de login;
- `password` nunca se serializa;
- se eliminan `App\Models\User`, `users`, `UserFactory` y referencias del seeder;
- se conserva un solo `UsuarioController` funcional en `API/V1`;
- se elimina el controlador de autenticacion duplicado por diferencia de mayusculas.

`config/auth.php` usa `Usuario` como provider predeterminado.

### Endpoints

Publicos:

- `POST /api/v1/login`;
- consultas GET del catalogo: hamacas, categorias, tamanos, colores y fotos.

Protegidos con `auth:sanctum`:

- `GET /api/v1/me`;
- `POST /api/v1/logout`;
- inventario;
- movimientos;
- POS;
- facturas;
- administracion de catalogos y usuarios.

### Roles

- `admin`: acceso administrativo completo;
- `vendedor`: crear ventas POS y consultar facturas e inventario;
- `almacenista`: crear, modificar y transferir inventario;
- `socio`: consultar el inventario que le pertenece.

La autorizacion verifica el rol del usuario, no solo las abilities del token. Sanctum emite tokens administrativos y permite ampliar abilities en integraciones futuras.

Los usuarios inactivos no pueden iniciar sesion. Al desactivar un usuario se revocan sus tokens.

## Estrategia de migrations

La base es de prueba y puede reconstruirse. Se reemplaza la dependencia del esquema manual por migrations completas y ordenadas.

Reglas:

- usar `increments()` e `integer()` para las tablas de dominio que conservan INT;
- no usar `foreignId()` contra claves INT heredadas;
- declarar columnas antes de `foreign()`;
- crear tablas padre antes de tablas hijas;
- eliminar tablas hijas antes de tablas padre en `down()`;
- asignar nombres de constraints predecibles cuando facilite mantenimiento;
- incluir todas las foreign keys, incluidas `hamaca_foto.foto_id`;
- evitar que una migration marcada como ejecutada o una tabla parcial oculte errores.

Las tablas internas de Laravel pueden conservar BIGINT cuando no referencian las tablas de dominio. La relacion polimorfica de Sanctum funciona con IDs INT de `usuarios` porque `tokenable_id` puede almacenar ese rango; no se crea una foreign key polimorfica.

La reconstruccion final se ejecuta con:

`php artisan migrate:fresh --seed`

solo contra la base de prueba confirmada.

## API y nombres

Se corrigen nombres inconsistentes sin cambiar el idioma principal del dominio:

- `/ubicacions` pasa a `/ubicaciones`;
- `/fotos_hamacas` pasa a `/fotos`;
- controladores, recursos y relaciones obsoletos de `HamacaFoto` y `UsuarioHamaca` se eliminan;
- `InventarioHamacaController` expone rutas REST y una accion explicita de transferencia;
- las respuestas usan recursos sin contrasenas ni campos internos.

No se mantendran aliases de rutas antiguas porque la base y los consumidores actuales son de prueba.

## Documentacion OpenAPI

La API se documentara con `darkaonline/l5-swagger` 11.1, compatible con Laravel 12.1 o superior.

La documentacion:

- usa OpenAPI 3;
- se visualiza mediante Swagger UI en `/api/documentation`;
- expone el documento generado en JSON;
- agrupa endpoints por autenticacion, catalogo, usuarios, inventario, POS y facturas;
- describe parametros, cuerpos, validaciones, respuestas y errores principales;
- incluye ejemplos basados en los datos sembrados;
- declara un esquema HTTP Bearer para tokens Sanctum;
- marca explicitamente las rutas publicas y protegidas;
- documenta los roles requeridos por operacion.

Los controladores y Form Requests contienen la informacion fuente necesaria para generar el contrato. La generacion se valida con:

`php artisan l5-swagger:generate`

En entorno local la interfaz puede consultarse directamente. En otros entornos requiere un usuario administrativo autenticado o queda deshabilitada por configuracion. La documentacion nunca incluye contrasenas reales, tokens sembrados ni secretos de entorno.

## Datos de prueba

Los seeders generaran datos deterministas y faciles de reconocer:

- un usuario por rol con credenciales documentadas para desarrollo;
- socios Bradly, Jacksa y Eloisa;
- categorias, tamanos y ubicaciones, incluida `Mercado`;
- colores basicos;
- modelos de hamacas familiares y otros ejemplos;
- inventario con propietarios distintos en la misma ubicacion;
- grupos con composiciones de uno y cuatro colores;
- cantidades suficientes para probar ventas separadas;
- clientes registrados y consumidor final de ejemplo;
- al menos una factura POS con IVA y otra con IVA mas IR.

Las contrasenas se almacenan con hash y solo se muestran como credenciales de desarrollo en la salida del seeder o documentacion local.

## Pruebas

Se agregan pruebas automatizadas para:

- login valido, credenciales invalidas, usuario inactivo, `me` y logout;
- ausencia de `password` en respuestas;
- permisos por rol;
- catalogo GET publico y escrituras protegidas;
- fotos muchos a muchos;
- inventario con socio obligatorio;
- propietarios distintos para el mismo modelo, ubicacion y colores;
- composiciones de cuatro colores;
- agrupamiento por composicion normalizada;
- transferencias que conservan propietario y colores;
- venta de una unidad desde un grupo con cantidad mayor;
- stock insuficiente;
- bloqueo y rollback de venta ante error;
- calculo de descuento, IVA 15%, IR opcional 2% y total;
- snapshots historicos de detalles;
- generacion y consulta de factura imprimible;
- generacion valida del documento OpenAPI y presencia de las rutas principales;
- integridad de foreign keys y tipos relevantes.

## Criterios de aceptacion

El trabajo se considera terminado cuando:

- `migrate:fresh --seed` reconstruye la base sin depender de SQL manual;
- el esquema no contiene `users`, `usuario_hamaca`, `hamaca_color` ni `hamaca_fotos`;
- todas las foreign keys esperadas existen y tienen tipos compatibles;
- solo `Usuario` autentica al personal con Sanctum;
- el inventario distingue modelo, socio, ubicacion y composicion;
- una venta POS descuenta stock y genera factura, detalles y movimientos atomicamente;
- los impuestos se calculan en el servidor y el IR es opcional;
- Swagger UI documenta y permite probar la API con un token Sanctum;
- los datos sembrados permiten probar todos los casos principales;
- la suite automatizada pasa.
