<?php

namespace Tests\Feature\Documentation;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_openapi_json_includes_auth_and_sales_paths(): void
    {
        $this->getJson('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('components.securitySchemes.bearerAuth.type', 'http')
            ->assertJsonPath('paths./v1/login.post.summary', 'Login administrativo')
            ->assertJsonPath('paths./v1/hamacas/monthly-inventory.get.summary', 'Inventario inicial mensual')
            ->assertJsonPath('paths./v1/movimientos/monthly-entries.get.summary', 'Entradas mensuales')
            ->assertJsonPath('paths./v1/inventario-hamacas.get.summary', 'Listar inventario')
            ->assertJsonPath('paths./v1/pos/ventas.post.summary', 'Registrar venta POS')
            ->assertJsonPath('paths./v1/facturas.get.summary', 'Listado de facturas');
    }

    public function test_documentation_ui_is_available(): void
    {
        $this->get('/api/v1/documentation')
            ->assertOk()
            ->assertSee('SwaggerUIBundle', false);
    }
}
