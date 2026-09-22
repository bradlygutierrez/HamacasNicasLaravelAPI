<?php

namespace Tests\Feature\Sales;

use App\Models\Factura;
use App\Models\FacturaServicio;
use App\Models\Foto;
use App\Models\DetalleFactura;
use App\Models\DetalleFacturaServicio;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Models\ProformaDetalleServicio;
use App\Models\Usuario;
use App\Services\Documents\PdfImageResolver;
use App\Services\Documents\FacturaPdfService;
use App\Services\Documents\ProformaPdfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Support\BuildsInventoryFixtures;
use Tests\TestCase;

class PdfDocumentTest extends TestCase
{
    use BuildsInventoryFixtures;
    use DatabaseTransactions;

    public function test_proforma_pdf_respects_access_and_response_headers(): void
    {
        $admin = $this->userWithRole('admin');
        $vendor = $this->userWithRole('vendedor');
        $socio = $this->userWithRole('socio');
        $warehouse = $this->userWithRole('almacenista');
        $proforma = $this->proforma($vendor, 'PRO-2026-000001');

        Sanctum::actingAs($admin);
        $this->get('/api/v1/proformas/' . $proforma->id . '/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'inline; filename="PRO-2026-000001.pdf"');
        $this->get('/api/v1/proformas/' . $proforma->id . '/pdf?download=1')->assertHeader('Content-Disposition', 'attachment; filename="PRO-2026-000001.pdf"');

        Sanctum::actingAs($vendor);
        $this->get('/api/v1/proformas/' . $proforma->id . '/pdf')->assertOk();
        Sanctum::actingAs($socio);
        $this->get('/api/v1/proformas/' . $proforma->id . '/pdf')->assertOk();
        Sanctum::actingAs($warehouse);
        $this->get('/api/v1/proformas/' . $proforma->id . '/pdf')->assertForbidden();
    }

    public function test_vendor_cannot_render_another_vendors_proforma_and_draft_is_marked(): void
    {
        $owner = $this->userWithRole('vendedor');
        $other = $this->userWithRole('vendedor');
        $draft = $this->proforma($owner, null);
        $detail = ProformaDetalle::create(['proforma_id' => $draft->id, 'receta_version_snapshot' => 1, 'hamaca_nombre_snapshot' => 'Nombre histórico', 'hamaca_descripcion_snapshot' => 'Descripción histórica', 'cantidad' => 2, 'precio_unitario' => 700, 'subtotal' => 1400]);
        $model = app(ProformaPdfService::class)->viewModel($draft->fresh());
        $this->assertTrue($model['isDraft']);
        $this->assertSame('Nombre histórico', $model['details'][0]['name']);
        $this->assertSame('Descripción histórica', $model['details'][0]['description']);
        $this->assertSame([], $model['sheets'][0]['photos'] ?? []);

        Sanctum::actingAs($other);
        $this->get('/api/v1/proformas/' . $draft->id . '/pdf')->assertForbidden();
        Sanctum::actingAs($owner);
        $this->get('/api/v1/proformas/' . $draft->id . '/pdf')->assertOk();
    }

    public function test_invoice_pdf_uses_invoice_access_rules_and_filename(): void
    {
        $owner = $this->userWithRole('vendedor');
        $other = $this->userWithRole('vendedor');
        $socio = $this->userWithRole('socio');
        $warehouse = $this->userWithRole('almacenista');
        $invoice = Factura::create(['numero' => 'FAC-' . uniqid(), 'vendedor_id' => $owner->id, 'origen' => 'venta_directa', 'canal' => 'pos', 'nombre_cliente' => 'Cliente snapshot', 'ruc' => 'RUC snapshot', 'direccion' => 'Dirección snapshot', 'telefono' => '8888', 'correo' => 'cliente@example.com', 'metodo_pago' => 'efectivo', 'subtotal' => 1000, 'descuento' => 50, 'tasa_iva' => .15, 'aplica_iva' => true, 'monto_iva' => 142.5, 'aplica_ir' => false, 'tasa_ir' => .02, 'monto_ir' => 0, 'total' => 1092.5, 'fecha' => now()]);
        $this->assertSame(now()->format('d/m/Y'), app(FacturaPdfService::class)->viewModel($invoice->fresh())['dateFormatted']);

        Sanctum::actingAs($owner);
        $this->get('/api/v1/facturas/' . $invoice->id . '/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Content-Disposition', 'inline; filename="' . $invoice->numero . '.pdf"');
        Sanctum::actingAs($other);
        $this->get('/api/v1/facturas/' . $invoice->id . '/pdf')->assertForbidden();
        Sanctum::actingAs($socio);
        $this->get('/api/v1/facturas/' . $invoice->id . '/pdf')->assertOk();
        Sanctum::actingAs($warehouse);
        $this->get('/api/v1/facturas/' . $invoice->id . '/pdf')->assertForbidden();
    }

    public function test_invoice_view_model_keeps_product_and_general_service_amounts(): void
    {
        $owner = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(4, $owner);
        $invoice = Factura::create(['numero' => 'FAC-' . uniqid(), 'vendedor_id' => $owner->id, 'origen' => 'pedido', 'pedido_id' => null, 'canal' => 'pos', 'nombre_cliente' => 'Cliente', 'subtotal' => 1200, 'descuento' => 0, 'tasa_iva' => .15, 'aplica_iva' => true, 'monto_iva' => 180, 'aplica_ir' => true, 'tasa_ir' => .02, 'monto_ir' => 24, 'total' => 1356, 'fecha' => now()]);
        $detail = DetalleFactura::create(['factura_id' => $invoice->id, 'inventario_hamaca_id' => $seed['inventario_id'], 'hamaca_id' => $seed['hamaca_id'], 'usuario_id' => $owner->id, 'ubicacion_id' => $seed['ubicacion_origen_id'], 'hamaca_nombre' => 'Producto snapshot', 'hamaca_descripcion' => 'Descripción snapshot', 'colores_snapshot' => json_encode(['Azul']), 'cantidad' => 1, 'precio_unitario' => 1000, 'descuento' => 0, 'subtotal' => 1000]);
        DetalleFacturaServicio::create(['detalle_factura_id' => $detail->id, 'servicio_nombre_snapshot' => 'Servicio producto', 'detalle' => 'Detalle producto', 'cantidad' => 1, 'precio_unitario' => 100, 'descuento' => 10, 'subtotal' => 90]);
        FacturaServicio::create(['factura_id' => $invoice->id, 'servicio_nombre_snapshot' => 'Servicio factura', 'detalle' => 'Detalle factura', 'cantidad' => 1, 'precio_unitario' => 150, 'descuento' => 0, 'subtotal' => 150]);
        $model = app(FacturaPdfService::class)->viewModel($invoice->fresh());
        $this->assertSame('Servicio producto', $model['details'][0]['services'][0]['name']);
        $this->assertSame('90.00', (string) $model['details'][0]['services'][0]['subtotal']);
        $this->assertSame('Servicio factura', $model['services'][0]['name']);
        $this->assertSame('150.00', (string) $model['services'][0]['subtotal']);
    }

    public function test_image_resolver_returns_null_for_missing_or_private_urls(): void
    {
        $resolver = app(PdfImageResolver::class);
        $this->assertNull($resolver->resolve('fotos/no-existe.png'));
        $this->assertNull($resolver->resolve('http://localhost/no-existe.png'));
    }

    public function test_proforma_sheets_prefer_variant_photos_then_fallback_to_hamaca_and_group_units(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $catalog = $this->catalogFixture();
        $proforma = $this->proforma($vendor, 'PRO-' . uniqid());
        Storage::fake('public');
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('public')->put('fotos/variant.png', $pixel);
        Storage::disk('public')->put('fotos/model.png', $pixel);
        $variantPhoto = Foto::create(['ruta' => 'fotos/variant.png']);
        $modelPhoto = Foto::create(['ruta' => 'fotos/model.png']);
        $variantPhoto->variantes()->attach($catalog['variante_id']);
        $modelPhoto->hamacas()->attach($catalog['hamaca_id']);
        ProformaDetalle::create(['proforma_id' => $proforma->id, 'hamaca_id' => $catalog['hamaca_id'], 'hamaca_variante_id' => $catalog['variante_id'], 'receta_version_snapshot' => 1, 'hamaca_nombre_snapshot' => 'Modelo snapshot', 'cantidad' => 6, 'precio_unitario' => 1, 'subtotal' => 6]);
        ProformaDetalle::create(['proforma_id' => $proforma->id, 'hamaca_id' => $catalog['hamaca_id'], 'hamaca_variante_id' => $catalog['variante_id'], 'receta_version_snapshot' => 1, 'hamaca_nombre_snapshot' => 'Modelo snapshot', 'cantidad' => 2, 'precio_unitario' => 1, 'subtotal' => 2]);
        $model = app(ProformaPdfService::class)->viewModel($proforma->fresh());
        $this->assertCount(1, $model['sheets']);
        $this->assertSame(8, (int) $model['sheets'][0]['quantity']);
        $this->assertCount(1, $model['sheets'][0]['photos']);
        $variantPhoto->variantes()->detach($catalog['variante_id']);
        $brokenPhoto = Foto::create(['ruta' => 'fotos/variant-file-missing.png']);
        $brokenPhoto->variantes()->attach($catalog['variante_id']);
        $fallback = app(ProformaPdfService::class)->viewModel($proforma->fresh());
        $this->assertCount(1, $fallback['sheets'][0]['photos']);
    }

    public function test_historical_null_foreign_keys_do_not_merge_different_product_snapshots(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $proforma = $this->proforma($vendor, 'PRO-' . uniqid());
        ProformaDetalle::create(['proforma_id' => $proforma->id, 'receta_version_snapshot' => 1, 'hamaca_nombre_snapshot' => 'Producto histórico A', 'cantidad' => 1, 'precio_unitario' => 10, 'subtotal' => 10]);
        ProformaDetalle::create(['proforma_id' => $proforma->id, 'receta_version_snapshot' => 1, 'hamaca_nombre_snapshot' => 'Producto histórico B', 'cantidad' => 1, 'precio_unitario' => 20, 'subtotal' => 20]);
        $model = app(ProformaPdfService::class)->viewModel($proforma->fresh());
        $this->assertCount(2, $model['sheets']);
    }

    public function test_proforma_view_model_keeps_product_service_amounts(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $proforma = $this->proforma($vendor, 'PRO-' . uniqid());
        $detail = ProformaDetalle::create(['proforma_id' => $proforma->id, 'receta_version_snapshot' => 1, 'hamaca_nombre_snapshot' => 'Producto', 'cantidad' => 2, 'precio_unitario' => 100, 'subtotal' => 200]);
        ProformaDetalleServicio::create(['proforma_detalle_id' => $detail->id, 'servicio_nombre_snapshot' => 'Orilla de lujo', 'alcance_snapshot' => 'producto', 'metodo_calculo_snapshot' => 'fijo', 'cantidad' => 2, 'precio_unitario' => 25, 'descuento' => 5, 'subtotal' => 45]);
        $model = app(ProformaPdfService::class)->viewModel($proforma->fresh());
        $this->assertSame('Orilla de lujo', $model['details'][0]['services'][0]['name']);
        $this->assertSame('45.00', (string) $model['details'][0]['services'][0]['subtotal']);
    }

    private function proforma(Usuario $vendor, ?string $number): Proforma
    {
        return Proforma::create(['numero' => $number, 'vendedor_id' => $vendor->id, 'estado' => $number ? 'emitida' : 'borrador', 'nombre_cliente' => 'Cliente proforma', 'fecha' => now()->toDateString(), 'valida_hasta' => now()->addDays(10)->toDateString(), 'subtotal_bruto' => 1400, 'base_neta' => 1400, 'total' => 1400]);
    }
}
