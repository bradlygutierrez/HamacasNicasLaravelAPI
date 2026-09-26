# Hamaca como Producto Único Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove `HamacaVariante` from backend and frontend runtime while promoting existing variants into real Hamacas and preserving production, stock, commercial records, and snapshots.

**Architecture:** Execute on `refactor/hamaca-producto-unico` in both repositories. Backend uses three forward-only incremental migrations: prepare pivots and a durable promotion map, promote each variant to a new Hamaca and remap dependent records, then remove variant-only schema after orphan checks. Application behavior moves ownership to Hamaca; frontend consumes the Hamaca-only contracts and presents one product creation/edit flow.

**Tech Stack:** Laravel, Eloquent, MySQL migrations, PHPUnit, Next.js, TypeScript, React, Node test runner, ESLint.

---

## Baseline and constraints

- Backend base: `983bfa5f4d6e79f4f86dc0fe2b7665f99b821778`.
- Frontend remote `main` is `e5d9e7b180c620529d7e3b0ac56ede81e65c43ff`, which includes the dashboard fix; the requested older `c6209e6...` is its parent. Keep the dashboard fix.
- Both worktrees are on `refactor/hamaca-producto-unico`; do not merge.
- Preserve the pre-existing frontend changes in `package-lock.json` and `public/favicon.svg:Zone.Identifier`.
- Never run `migrate:fresh`, never modify historical migrations, never touch WordPress, sidebar visuals, or commercial snapshots.
- Migration 3 is explicitly irreversible. Test migrations only against local/test databases.

## Audited code surface

Backend hits span `Hamaca`, `HamacaVariante`, `Color`, `Foto`, `InventarioHamaca`, `RecetaHamaca`, `PedidoDetalle`, `ProformaDetalle`; API controllers, requests and resources for Hamaca, formulas, inventory, proformas, pedidos and photos; recipe, inventory, pricing, proforma, order billing and PDF services; API routes; `DatabaseSeeder`; schema, inventory, production, POS, PDF and sales feature tests; and the historical variant plan/spec documents. Historical migrations and the explicitly historical plan remain searchable by design.

Frontend hits span catalog, formula list/editor, inventory, POS sales, order billing, proforma editor, entry and photo/variant modals, Hamaca/entry helpers, and tests for entries, formulas, proformas and toast behavior. Replace variant-specific UI modules and remove them when unused.

## Task 1: Add migration coverage and prepare schema

**Backend files:** `tests/Feature/Database/SchemaTest.php`, new focused migration feature test, new migration `prepare_single_hamaca_products`.

- [ ] Write migration tests for `hamaca_color` composite key/FKs and `hamaca_variant_promotion_map` key/uniqueness.
- [ ] Run the focused test and confirm it fails because the new schema is absent.
- [ ] Add only the preparation migration; leave all production data untouched in this stage.
- [ ] Run focused migration tests and the existing schema test.

## Task 2: Promote variants and preserve every reference

**Backend files:** new promotion migration; focused migration feature test; any narrowly scoped migration helper under `database/migrations` if needed.

- [ ] Build fixtures for two variants with different colors, photos, recipes, inventory, proforma details, pedido details and inventory references. Capture all IDs and snapshot values before migration.
- [ ] Add failing assertions for promoted product names, inactive soft deletion, parent soft deletion, preserved recipe/inventory IDs, remapped details, and unchanged snapshots.
- [ ] Add failing cases for photo fallback, legacy parent with no variants, ambiguous parent colors aborting, unmapped foreign keys aborting, and duplicate inventory consolidation with reference reassignment.
- [ ] Promote every variant to a new Hamaca; persist the exact `(hamaca_variante_id, original_hamaca_id, promoted_hamaca_id)` mapping and use it for all remaps.
- [ ] Copy variant colors and photos; use parent photos only when a variant has none. Use inventory-color fallback only when it is unambiguous.
- [ ] Move recipes and stock in place without changing IDs. Remap proforma/order details without touching snapshots. Consolidate duplicate inventory by smallest inventory ID and reassign all discovered inventory foreign keys before deleting duplicates.
- [ ] Soft-delete variant-bearing parent Hamacas; keep parents with no variants, aborting on incompatible legacy color compositions.
- [ ] Run the migration feature tests and verify every preservation assertion.

## Task 3: Remove variant schema safely

**Backend files:** new cleanup migration; migration feature test; schema test.

- [ ] Assert zero unmapped variant-linked rows and zero inventory uniqueness conflicts before any destructive schema operation; throw with table and IDs when violated.
- [ ] Add migration tests for recipe uniqueness by `(hamaca_id, version)`, inventory uniqueness by `(hamaca_id, usuario_id, ubicacion_id)`, and removal of variant tables/columns.
- [ ] Drop variant FKs/columns and variant-only tables, `inventario_hamaca_color`, and the temporary promotion map; retain colors, photos and `hamaca_foto`.
- [ ] Implement the required irreversible `down()` exception with the exact user-specified message.
- [ ] Run focused tests and inspect the resulting schema on local/test only.

## Task 4: Make Hamaca the backend product aggregate

**Backend files:** `app/Models/{Hamaca,Color,Foto,InventarioHamaca,RecetaHamaca,PedidoDetalle,ProformaDetalle}.php`; Hamaca and inventory resources/controllers/requests; `routes/api.php`; remove variant model/controller/resource.

- [ ] Add/adjust failing model and API tests for Hamaca colors/photos, full create/update, generated names, manual names, soft deletion, and history-based classification/color guards.
- [ ] Add the reusable Hamaca name suggestion service and transactional Hamaca create/update flows using the existing photo storage mechanism.
- [ ] Move color, photo, recipe and inventory relationships to Hamaca; remove variant relationships and fields from runtime resources.
- [ ] Expose recipe list/active/create routes under `/v1/hamacas/{hamaca}/recetas`; retain recipe-ID operations. Validate first-formula `source_hamaca_id` eligibility and keep version cloning scoped to its own Hamaca.
- [ ] Update the formulas API to one row per active Hamaca with category, size, colors, recipe states and costs; search by product/category/size/color.
- [ ] Remove `/v1/hamaca-variantes` routes and all runtime variant classes/imports.
- [ ] Run relevant Hamaca, recipe and schema tests.

## Task 5: Move inventory, sales, orders and documents to Hamaca

**Backend files:** inventory/proforma/order/facturation/POS controllers, requests, resources and services; `ProformaPdfService`; related feature tests.

- [ ] Add failing tests for inventory entry/transfer keyed by Hamaca, POS sale without a formula, and colors sourced from `Hamaca::colores`.
- [ ] Remove variant/color-composition payloads and use `(hamaca_id, usuario_id, ubicacion_id)` throughout inventory lookup and locking.
- [ ] Make proforma product lookup and pricing use active Hamacas and `recetaActiva`; require `hamaca_id` in new detail payloads and preserve emitted historical documents by snapshot.
- [ ] Make order creation and billing use `pedido_detalle.hamaca_id`; remove variant selection from billing while preserving generated invoice snapshots and inventory movement behavior.
- [ ] Remove variant photo priority from PDFs; use Hamaca photos while leaving all commercial values and snapshots untouched.
- [ ] Run inventory, POS, proforma, pedido, billing, and PDF feature tests.

## Task 6: Remove variants from frontend flows

**Frontend files:** catalog, formula list/editor, proforma editor, inventory and entry flows, sales POS, order detail/billing, affected `_components` and `_lib` helpers/tests.

- [ ] Add failing tests for the single Hamaca form, suggested-name updates/manual override/reset, direct color/photo selection, and Hamaca-ID API payloads.
- [ ] Replace model-plus-variant creation with one Hamaca create/edit form containing name, category, size, colors, price, description and photos. Keep the existing sidebar and responsive POS design intact.
- [ ] Update catalog rows, formula list/editor, inventory entry, proforma selector, POS display and order billing to use Hamaca IDs/colors without variant state.
- [ ] Remove variant-only components, types, helpers and endpoint calls when no longer referenced.
- [ ] Run frontend tests, lint and production build; review responsive POS and sidebar files for unintended visual edits.

## Task 7: Update API documentation, seeders and all remaining tests

**Files:** backend `DocumentationController`, `docs/frontend-api-contract.md`, `DatabaseSeeder`, affected backend/frontend tests.

- [ ] Convert seeder fixtures to direct Hamaca colors, photos, recipes and inventory without variant schema.
- [ ] Document Hamaca product fields, colors/photos, Hamaca recipe routes, inventory identity and proforma payloads; remove current variant contracts. Retain historical migration documentation only where clearly labeled historical.
- [ ] Update all affected tests to direct Hamaca setup and add the specified API behavior coverage.
- [ ] Run backend `php artisan test` and frontend `npm test`, `npm run lint`, and `npm run build`; report actual results only.

## Task 8: Final audit and delivery

- [ ] Run the required runtime searches in backend `app routes config` and frontend `app`; expected result is zero references to `HamacaVariante`, `hamaca_variante_id`, `hamaca-variantes`, and `composicion_clave`.
- [ ] Search `variante` manually and remove stale current-contract/UI references. Historical migrations and the historical variant plan may retain references.
- [ ] Validate schema on local/test only; do not run migration against production.
- [ ] Review both diffs, preserve the unrelated frontend worktree changes, and verify both branches remain unmerged.
- [ ] Push each branch to origin and report backend/frontend SHAs, migration names, preservation approach, promoted fixture counts, suggested-name behavior, and exact command results.
