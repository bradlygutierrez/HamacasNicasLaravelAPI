# Fórmulas por variante Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mover las recetas nuevas al nivel `HamacaVariante`, preservar recetas legacy e históricos y actualizar API/frontend para operar por variante.

**Architecture:** Una migración incremental agrega `hamaca_variante_id`, elimina el unique anterior y crea el unique por variante/version después de clonar datos legacy por cada variante existente. La lógica nueva recibe una variante como agregado dueño; las recetas sin variante permanecen consultables por id, pero no participan en listados ni en operaciones nuevas.

**Tech Stack:** Laravel 12, Eloquent, MySQL, PHPUnit/Pest-style Feature tests, Next.js/React, TypeScript y tests Node source-based.

---

### Task 1: Baseline y contrato de migración

**Files:**
- Create: `database/migrations/2026_09_22_000001_move_recipes_to_variants.php`
- Test: `tests/Feature/Production/VariantRecipeMigrationTest.php`

- [ ] Escribir pruebas para conservar recetas legacy, clonar materiales/mano de obra por variante y no crear clones cuando la Hamaca no tiene variantes.
- [ ] Ejecutar la prueba y confirmar el fallo por ausencia de `hamaca_variante_id`/unique nuevo.
- [ ] Implementar la migración incremental: agregar FK nullable, clonar cada legacy a cada variante existente dentro de transacción, copiar columnas y detalles, eliminar unique viejo, crear unique `(hamaca_variante_id, version)` permitiendo legacy null mediante índice compatible con MySQL.
- [ ] Ejecutar la prueba de migración en una base preparada sin `migrate:fresh` sobre datos históricos de fixture.

### Task 2: Modelos y servicio de recetas por variante

**Files:**
- Modify: `app/Models/HamacaVariante.php`
- Modify: `app/Models/RecetaHamaca.php`
- Modify: `app/Models/Hamaca.php`
- Modify: `app/Services/RecetaHamacaService.php`
- Test: `tests/Feature/Production/RecetaHamacaApiTest.php`

- [ ] Agregar primero casos fallidos para dos variantes independientes, borrador único, activa única, clonado de versión y copia opcional desde otra variante del mismo modelo.
- [ ] Cambiar el servicio para bloquear la variante, calcular versiones por variante, clonar su activa y validar `source_variant_id` de la misma Hamaca.
- [ ] Agregar relaciones `HamacaVariante::recetas/recetaActiva` y `RecetaHamaca::hamacaVariante`; conservar `Hamaca::recetas/recetaActiva` solo para legacy histórico sin usarlo en lógica nueva.
- [ ] Asegurar que activar/descartar/editar por recipe id respete el dueño variante y que activar una variante no archive otra.

### Task 3: Rutas, requests, resources y listado de fórmulas

**Files:**
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/API/V1/RecetaHamacaController.php`
- Modify: `app/Http/Controllers/API/V1/FormulaController.php`
- Modify: `app/Http/Resources/V1/RecetaHamacaResource.php`
- Modify: `app/Http/Controllers/API/V1/DocumentationController.php`
- Test: `tests/Feature/Production/RecetaHamacaApiTest.php`

- [ ] Probar primero endpoints variante y confirmar que las rutas antiguas no crean recetas nuevas.
- [ ] Implementar GET activa/index/POST bajo `/hamaca-variantes/{hamacaVariante}/recetas`, con `source_variant_id` validado.
- [ ] Hacer que `/formulas` devuelva una fila por variante con Hamaca, colores y solo activa/borrador; excluir legacy sin variante.
- [ ] Mantener endpoints por recipe id y actualizar OpenAPI.

### Task 4: Proforma por variante

**Files:**
- Modify: `app/Services/ProformaPricingService.php`
- Modify: `app/Http/Requests/*Proforma*Request.php`
- Modify: `app/Http/Controllers/API/V1/ProformaController.php`
- Test: `tests/Feature/Sales/ProformaApiTest.php`

- [ ] Escribir casos fallidos para variante activa distinta, variante sin fórmula, variante de otra Hamaca y ausencia de fallback a Hamaca.
- [ ] Validar variante perteneciente a `hamaca_id`, activa y state=true; usar únicamente `variante->recetaActiva`.
- [ ] Mantener snapshots y referencias históricas sin recalcular operaciones existentes.
- [ ] Confirmar que POS/Inventario no exige fórmula.

### Task 5: Frontend de fórmulas por variante

**Files:**
- Modify: `app/(sidebar-pages)/formulas/page.tsx`
- Modify: `app/(sidebar-pages)/formulas/[hamacaId]/page.tsx` (renombrar segmento si necesario sin romper navegación)
- Modify: `tests/phase2-recetas.test.ts`

- [ ] Actualizar tests source para variante, nombre/colores, estados, rutas nuevas y selector opcional de copia.
- [ ] Cambiar tipos/listado para agrupar variantes por Hamaca y operar con `hamaca_variante_id`.
- [ ] Mostrar encabezado de modelo + variante y mantener labels UX existentes.
- [ ] Implementar “Copiar fórmula de otra variante” solo con variantes del mismo modelo que tengan activa.
- [ ] Verificar que Hamaca no tenga selector genérico y que el editor no permita cambiar variante.

### Task 6: Validación integral y entrega

**Files:**
- Modify only files required by failing tests.

- [ ] Ejecutar `php artisan test` y `npm test`.
- [ ] Ejecutar `npm run lint` y `npm run build`.
- [ ] Ejecutar `git diff --check`, revisar migración sin `migrate:fresh`, y comprobar que no se tocaron sidebar/POS/PDF.
- [ ] Commit separado backend/frontend y push de ambas ramas nuevas; reportar SHA y resultados reales sin mergear.
