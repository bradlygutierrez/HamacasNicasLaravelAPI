# Inventario, POS, Facturacion y Auth Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reconstruir la API Laravel con un esquema reproducible, autenticacion administrativa Sanctum, inventario multicolor por socio, ventas POS transaccionales, facturacion y documentacion Swagger.

**Architecture:** El dominio usa modelos Eloquent con IDs INT y servicios transaccionales para inventario y ventas. Las rutas publicas exponen catalogos de lectura; las rutas administrativas usan Sanctum y middleware de roles. La documentacion OpenAPI se genera desde atributos PHP con L5-Swagger.

**Tech Stack:** PHP 8.4, Laravel 12, MySQL 8, SQLite en memoria para pruebas, Laravel Sanctum 4.3, L5-Swagger 11.1, PHPUnit 11.

---

### Task 1: Esquema reproducible

**Files:**
- Replace: `database/migrations/0001_01_01_000000_create_users_table.php`
- Keep: `database/migrations/0001_01_01_000001_create_cache_table.php`
- Keep: `database/migrations/0001_01_01_000002_create_jobs_table.php`
- Delete: migrations parciales de abril y mayo de 2026
- Create: `database/migrations/2026_01_01_000003_create_catalog_and_inventory_tables.php`
- Create: `database/migrations/2026_01_01_000004_create_sales_tables.php`
- Test: `tests/Feature/Database/SchemaTest.php`

- [ ] Escribir pruebas que ejecuten migrations y verifiquen tablas, ausencia de tablas obsoletas, columnas INT compatibles, FKs y unicidad de inventario.
- [ ] Ejecutar `php artisan test tests/Feature/Database/SchemaTest.php` y confirmar fallo por esquema incompleto.
- [ ] Crear `usuarios`, tablas de catalogo, `hamacas`, `fotos`, pivotes, inventario, colores de inventario, clientes, facturas, detalles y movimientos en orden de dependencia.
- [ ] Usar `increments()` e `integer()` en tablas de dominio; mantener BIGINT solo en tablas internas de Laravel.
- [ ] Ejecutar la prueba y confirmar que pasa.

### Task 2: Usuario unico, Sanctum y roles

**Files:**
- Modify: `app/Models/Usuario.php`
- Delete: `app/Models/User.php`
- Delete: `database/factories/UserFactory.php`
- Modify: `database/factories/UsuarioFactory.php`
- Modify: `config/auth.php`
- Create: `app/Http/Middleware/EnsureRole.php`
- Modify: `bootstrap/app.php`
- Consolidate: `app/Http/Controllers/API/V1/AuthController.php`
- Delete: `app/Http/Controllers/API/V1/AuthCOntroller.php`
- Modify: `app/Http/Controllers/API/V1/UsuarioController.php`
- Modify: `app/Http/Resources/V1/UsuarioResource.php`
- Test: `tests/Feature/Auth/AdministrativeAuthTest.php`
- Test: `tests/Feature/Auth/RoleAuthorizationTest.php`

- [ ] Escribir pruebas para login, usuario inactivo, `me`, logout, ocultamiento de password y permisos por rol.
- [ ] Ejecutar las pruebas y confirmar fallos por provider, rutas y middleware faltantes.
- [ ] Configurar `Usuario` como unico provider, password hasheado, tokens Sanctum y revocacion al desactivar.
- [ ] Registrar middleware `role` y proteger operaciones segun `admin`, `vendedor`, `almacenista` y `socio`.
- [ ] Ejecutar pruebas y confirmar que pasan.

### Task 3: Catalogo y fotos muchos a muchos

**Files:**
- Modify: `app/Models/Hamaca.php`
- Modify: `app/Models/Foto.php`
- Modify: `app/Models/Color.php`
- Modify: `app/Models/Ubicacion.php`
- Delete: `app/Models/HamacaFoto.php`
- Delete: `app/Models/HamacaColor.php`
- Delete: `app/Models/UsuarioHamaca.php`
- Replace: `app/Http/Controllers/API/V1/FotoController.php`
- Delete: `app/Http/Controllers/API/V1/HamacaFotoController.php`
- Delete: recursos obsoletos de HamacaFoto y UsuarioHamaca
- Modify: `app/Http/Controllers/API/V1/HamacaController.php`
- Modify: `app/Http/Resources/V1/HamacaResource.php`
- Modify: `app/Http/Resources/V1/FotoResource.php`
- Test: `tests/Feature/Catalog/CatalogApiTest.php`
- Test: `tests/Feature/Catalog/PhotoRelationshipTest.php`

- [ ] Escribir pruebas para GET publico, escrituras protegidas y reutilizacion de una foto en varias hamacas.
- [ ] Ejecutar pruebas y confirmar fallos.
- [ ] Corregir relaciones Eloquent y endpoints REST `/fotos`, incluyendo adjuntar y separar hamacas sin duplicar fotos.
- [ ] Eliminar referencias a relaciones obsoletas.
- [ ] Ejecutar pruebas y confirmar que pasan.

### Task 4: Inventario multicolor transaccional

**Files:**
- Modify: `app/Models/InventarioHamaca.php`
- Create: `app/Services/InventarioService.php`
- Create: `app/Http/Requests/StoreInventarioHamacaRequest.php`
- Create: `app/Http/Requests/TransferInventarioRequest.php`
- Replace: `app/Http/Controllers/API/V1/InventarioHamacaController.php`
- Modify: `app/Http/Resources/V1/InventarioHamacaResource.php`
- Test: `tests/Feature/Inventory/InventoryApiTest.php`
- Test: `tests/Feature/Inventory/InventoryTransferTest.php`

- [ ] Escribir pruebas para socio obligatorio, cuatro colores, propietarios distintos, agrupamiento por colores normalizados y transferencia.
- [ ] Ejecutar pruebas y confirmar fallos.
- [ ] Implementar clave SHA-256 de IDs de colores ordenados y servicio con transacciones y bloqueo.
- [ ] Implementar alta/incremento, consulta filtrada para socios, actualizacion y transferencia conservando propietario y colores.
- [ ] Ejecutar pruebas y confirmar que pasan.

### Task 5: POS y facturacion

**Files:**
- Modify: `app/Models/Factura.php`
- Modify: `app/Models/DetalleFactura.php`
- Modify: `app/Models/Movimiento.php`
- Create: `app/Models/Cliente.php`
- Create: `app/Services/VentaService.php`
- Create: `app/Http/Requests/StoreVentaRequest.php`
- Create: `app/Http/Controllers/API/V1/PosVentaController.php`
- Replace: `app/Http/Controllers/API/V1/FacturaController.php`
- Modify: `app/Http/Controllers/API/V1/MovimientoController.php`
- Replace: `app/Http/Resources/V1/FacturaResource.php`
- Modify: `app/Http/Resources/V1/MovimientoResource.php`
- Test: `tests/Feature/Sales/PosSaleTest.php`
- Test: `tests/Feature/Sales/InvoiceCalculationTest.php`

- [ ] Escribir pruebas para venta parcial, stock insuficiente, rollback, snapshots, IVA 15%, IR opcional 2% y consulta imprimible.
- [ ] Ejecutar pruebas y confirmar fallos.
- [ ] Implementar `VentaService` con `DB::transaction`, `lockForUpdate`, precio del servidor y calculos monetarios redondeados a dos decimales.
- [ ] Crear factura, detalles, movimientos y descuento de stock atomicamente.
- [ ] Exponer `POST /api/v1/pos/ventas` y lectura de facturas; impedir edicion manual de facturas emitidas.
- [ ] Ejecutar pruebas y confirmar que pasan.

### Task 6: Rutas, seeders y Swagger

**Files:**
- Replace: `routes/api.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create or modify: factories de modelos usados por pruebas
- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `config/l5-swagger.php` mediante publicacion del paquete
- Create: `app/OpenApi/OpenApiDefinition.php`
- Modify: controladores/requests con atributos OpenAPI
- Modify: `README.md`
- Test: `tests/Feature/Documentation/OpenApiTest.php`
- Test: `tests/Feature/Database/SeederTest.php`

- [ ] Escribir pruebas para rutas publicas/protegidas, datos deterministas y documento OpenAPI con login, inventario, POS y facturas.
- [ ] Ejecutar pruebas y confirmar fallos.
- [ ] Instalar L5-Swagger 11.1 y publicar configuracion.
- [ ] Definir esquema Bearer, tags, componentes y operaciones principales.
- [ ] Crear seeders con roles, socios Bradly/Jacksa/Eloisa, Mercado, composiciones multicolor, clientes y facturas ejemplo.
- [ ] Documentar credenciales de desarrollo y URL Swagger.
- [ ] Ejecutar `php artisan l5-swagger:generate` y las pruebas.

### Task 7: Reconstruccion y verificacion final

**Files:**
- Review: todos los archivos modificados

- [ ] Ejecutar `vendor/bin/pint --test`.
- [ ] Ejecutar `php artisan test`.
- [ ] Ejecutar `php artisan migrate:fresh --seed` contra MySQL de prueba.
- [ ] Consultar `information_schema` para verificar tipos y foreign keys.
- [ ] Ejecutar `php artisan l5-swagger:generate`.
- [ ] Ejecutar `php artisan route:list -v` y confirmar middleware.
- [ ] Revisar `git diff --check` y buscar referencias a `User`, `contraseña`, `usuario_hamaca`, `hamaca_color`, `hamaca_fotos` y rutas antiguas.
