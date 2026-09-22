# Ventas POS UI Design

**Goal:** Convert `/ventas` into a direct-sales POS while preserving invoice consultation and the separate Pedido → Factura flow.

**Scope:** Direct POS sales only. No PDF, credit, returns, accounts receivable, sidebar redesign, Clients redesign, or changes to `PedidoFacturacionService`.

## Backend design

`VentaService` will expose a pure calculation path and a transactional persistence path. Both will resolve inventory prices from locked inventory records and use `DecimalMoney`; the frontend will never provide prices. The preview endpoint will run validation and calculation without creating invoices, details, movements, or changing stock. The real endpoint will reuse the same calculation result inside its transaction, then create the invoice and one `salida` movement per grouped inventory item.

Commercial rates will live in `config/comercial.php` as percentage values from `COMMERCIAL_IVA_RATE` and `COMMERCIAL_IR_RATE`. Stored invoice rates remain fractions (`0.1500`, `0.0200`) to match the existing schema. `aplica_iva` and `aplica_ir` independently control their amounts.

`FacturaController` will provide paginated, role-safe index filtering by `search` and `origen`, plus a detail endpoint that loads invoice relations explicitly. Existing Pedido billing routes and services remain unchanged.

## Frontend design

`/ventas` will retain the current invoice list/detail shell and add a permission-gated direct-sale form. The form will load only positive-stock inventory, select registered/manual clients, send inventory IDs and quantities only, call the preview endpoint for the summary, and post the final payload to `/pos/ventas`. On success it will show the requested toast, close/reset the form, reload inventory and invoices, and select the returned invoice.

## Verification

Backend tests cover role access, calculation without mutation, configurable IVA/IR, stock locking/rollback, grouped movements, snapshots, origin and invoice numbering. Frontend tests cover permissions, inventory selection, quantity limits, payloads, preview, filters, pagination, client modes, and post-sale selection.
