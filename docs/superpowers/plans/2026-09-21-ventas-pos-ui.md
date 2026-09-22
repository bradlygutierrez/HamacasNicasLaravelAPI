# Ventas POS UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a usable direct-sale POS to `/ventas` and complete the invoice query API without changing Pedido billing.

**Architecture:** Reuse `VentaService` for pure preview and transactional sale creation. Add only the missing invoice query/preview endpoints and keep the existing API/resource patterns. Implement the POS form inside the existing `/ventas` route using current permission, API, toast, and client-registration patterns.

**Tech Stack:** Laravel, FormRequest, Eloquent, DecimalMoney/BCMath, Next.js App Router, TypeScript, existing `apiFetch`, Node source tests.

---

### Task 1: Backend contracts and configurable commercial rates

**Files:** `config/comercial.php`, `.env.example`, `app/Http/Requests/StoreVentaRequest.php`, new preview request if needed, backend feature tests.

- [ ] Add `COMMERCIAL_IVA_RATE` and `COMMERCIAL_IR_RATE` percentage configuration with defaults matching current behavior.
- [ ] Validate `aplica_iva`, `aplica_ir`, client snapshot fields, grouped distinct inventory IDs, and positive quantities.
- [ ] Add failing tests for IVA opt-out, configured rates, and preview validation.
- [ ] Run focused tests and implement the request/config contract.

### Task 2: Extract safe POS calculation and preview

**Files:** `app/Services/VentaService.php`, `app/Http/Controllers/API/V1/PosVentaController.php`, `routes/api.php`.

- [ ] Add a calculation method that locks inventory, resolves current prices, groups repeated IDs, validates stock/discount, and returns decimal subtotal/base/taxes/total without writes.
- [ ] Make IVA conditional and IR configurable while preserving fraction storage.
- [ ] Add `POST /v1/pos/ventas/calcular` using the same validated payload and return `data` values.
- [ ] Reuse the calculation result in `crearVenta()` and keep all invoice/detail/movement/stock writes inside the existing transaction.
- [ ] Set direct-sale `origen` and null `pedido_id` explicitly.

### Task 3: Complete invoice query API

**Files:** `app/Http/Controllers/FacturaController.php`, `app/Http/Resources/V1/FacturaResource.php`, `routes/api.php`, backend invoice tests.

- [ ] Implement role-safe paginated index with `search`, `origen`, `page`, and `per_page`.
- [ ] Implement show with explicit eager loading of cliente, vendedor, pedido, detalles, detalle services, and general services.
- [ ] Preserve vendor ownership and deny almacenista through current authorization middleware.
- [ ] Add tests for filters, pagination, detail relations, and roles.

### Task 4: Frontend POS state and API interactions

**Files:** `app/(sidebar-pages)/ventas/page.tsx`, frontend tests.

- [ ] Add capability gating so only admin/vendor see `Nueva venta`.
- [ ] Add inventory search/selection from `/inventario-hamacas`, filtering visible quantity > 0 and preventing quantity over visible stock.
- [ ] Add registered/manual/Consumidor final client modes, reusing existing client quick-registration behavior.
- [ ] Build payload with only `inventario_hamaca_id` and `cantidad` per item; never send prices.
- [ ] Call `/pos/ventas/calcular` for the summary and `/pos/ventas` to confirm.
- [ ] After success toast, close/reset, reload invoices/inventory, and select the returned invoice.

### Task 5: Frontend invoice list/detail UX

**Files:** `app/(sidebar-pages)/ventas/page.tsx`, frontend tests.

- [ ] Add search, origin filter, real pagination, and query parameters to `/facturas`.
- [ ] Load `/facturas/{id}` when selecting a row and render client, seller, channel, payment method, pedido, products, product/general services, and totals.
- [ ] Preserve existing visual language without changing sidebar markup.

### Task 6: Full verification and delivery

- [ ] Run `composer validate` and `php artisan test`.
- [ ] Run `npm run lint`, `npm run build`, and all frontend tests.
- [ ] Review diff to confirm no `PedidoFacturacionService`, Clients, Fase 5, PDF, or sidebar visual changes.
- [ ] Commit backend and frontend separately and push both `feat/ventas-pos-ui` branches; report SHAs without merging.
