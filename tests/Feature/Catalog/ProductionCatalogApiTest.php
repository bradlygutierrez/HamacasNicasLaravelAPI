<?php

namespace Tests\Feature\Catalog;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductionCatalogApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_manage_material_and_price_history_is_consistent(): void
    {
        $admin = $this->seedUser('admin');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/materiales', [
            'codigo' => 'MANILA-001',
            'nombre' => 'Manila',
            'descripcion' => 'Rollo de manila',
            'unidad_consumo' => 'metro',
            'unidad_compra' => 'rollo',
            'contenido_por_compra' => 100,
            'precio_actual' => 600,
            'porcentaje_merma' => 2.5,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.nombre', 'Manila')
            ->assertJsonPath('data.state', true);

        $materialId = $response->json('data.id');
        $this->assertDatabaseCount('material_precios', 1);

        $this->putJson("/api/v1/materiales/{$materialId}", [
            'precio_actual' => 650,
        ])->assertOk();

        $this->assertDatabaseCount('material_precios', 2);

        $this->putJson("/api/v1/materiales/{$materialId}", [
            'precio_actual' => 650,
        ])->assertOk();

        $this->assertDatabaseCount('material_precios', 2);

        $this->deleteJson("/api/v1/materiales/{$materialId}")->assertOk();

        $this->assertDatabaseHas('materiales', [
            'id' => $materialId,
            'state' => 0,
        ]);
        $this->assertDatabaseCount('material_precios', 2);
    }

    public function test_material_requires_purchase_content_when_units_differ(): void
    {
        Sanctum::actingAs($this->seedUser('admin'));

        $this->postJson('/api/v1/materiales', [
            'nombre' => 'Manila sin conversión',
            'unidad_consumo' => 'metro',
            'unidad_compra' => 'rollo',
            'precio_actual' => 600,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('contenido_por_compra');
    }

    public function test_material_normalizes_null_waste_percentage_to_zero(): void
    {
        Sanctum::actingAs($this->seedUser('admin'));

        $response = $this->postJson('/api/v1/materiales', [
            'nombre' => 'Bolillo',
            'unidad_consumo' => 'unidad',
            'unidad_compra' => 'unidad',
            'precio_actual' => 10,
        ])->assertOk();

        $materialId = $response->json('data.id');

        $this->putJson("/api/v1/materiales/{$materialId}", [
            'porcentaje_merma' => null,
        ])->assertOk();

        $this->assertDatabaseHas('materiales', [
            'id' => $materialId,
            'porcentaje_merma' => 0,
        ]);
    }

    public function test_material_normalizes_equal_units_to_one_purchase_content(): void
    {
        Sanctum::actingAs($this->seedUser('admin'));

        $response = $this->postJson('/api/v1/materiales', [
            'nombre' => 'Bolillo',
            'unidad_consumo' => 'unidad',
            'unidad_compra' => 'unidad',
            'precio_actual' => 10,
        ])->assertOk();

        $this->assertDatabaseHas('materiales', [
            'id' => $response->json('data.id'),
            'contenido_por_compra' => 1,
        ]);
    }

    public function test_material_update_rejects_missing_content_when_units_differ(): void
    {
        Sanctum::actingAs($this->seedUser('admin'));

        $response = $this->postJson('/api/v1/materiales', [
            'nombre' => 'Bolillo',
            'unidad_consumo' => 'unidad',
            'unidad_compra' => 'unidad',
            'precio_actual' => 10,
        ])->assertOk();

        $this->putJson('/api/v1/materiales/' . $response->json('data.id'), [
            'unidad_consumo' => 'metro',
            'unidad_compra' => 'rollo',
            'contenido_por_compra' => null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('contenido_por_compra');
    }

    public function test_material_update_normalizes_equal_units_to_one(): void
    {
        Sanctum::actingAs($this->seedUser('admin'));

        $response = $this->postJson('/api/v1/materiales', [
            'nombre' => 'Manila',
            'unidad_consumo' => 'metro',
            'unidad_compra' => 'rollo',
            'contenido_por_compra' => 100,
            'precio_actual' => 600,
        ])->assertOk();

        $this->putJson('/api/v1/materiales/' . $response->json('data.id'), [
            'unidad_consumo' => 'unidad',
            'unidad_compra' => 'unidad',
            'contenido_por_compra' => null,
        ])->assertOk();

        $this->assertDatabaseHas('materiales', [
            'id' => $response->json('data.id'),
            'contenido_por_compra' => 1,
        ]);
    }

    public function test_admin_can_manage_service_price_history_and_process_catalog(): void
    {
        Sanctum::actingAs($this->seedUser('admin'));

        $serviceResponse = $this->postJson('/api/v1/servicios-adicionales', [
            'codigo' => 'ENVIO',
            'nombre' => 'Envío',
            'alcance' => 'pedido',
            'metodo_calculo' => 'manual',
            'precio_venta_actual' => 250,
            'costo_actual' => 180,
        ]);

        $serviceResponse->assertOk();
        $serviceId = $serviceResponse->json('data.id');
        $this->assertDatabaseCount('servicio_precios', 1);

        $this->putJson("/api/v1/servicios-adicionales/{$serviceId}", [
            'costo_actual' => 200,
        ])->assertOk();

        $this->assertDatabaseCount('servicio_precios', 2);

        $this->putJson("/api/v1/servicios-adicionales/{$serviceId}", [
            'nombre' => 'Envío nacional',
        ])->assertOk();

        $this->assertDatabaseCount('servicio_precios', 2);

        $processResponse = $this->postJson('/api/v1/procesos-produccion', [
            'codigo' => 'TEJ',
            'nombre' => 'Tejido',
            'descripcion' => 'Tejido de la hamaca',
        ]);

        $processResponse->assertCreated()
            ->assertJsonPath('data.nombre', 'Tejido');

        $processId = $processResponse->json('data.id');
        $this->putJson("/api/v1/procesos-produccion/{$processId}", [
            'nombre' => 'Tejido artesanal',
        ])->assertOk();

        $this->deleteJson("/api/v1/procesos-produccion/{$processId}")->assertOk();
        $this->assertDatabaseHas('procesos_produccion', [
            'id' => $processId,
            'state' => 0,
        ]);
    }

    public function test_unauthorized_roles_cannot_manage_catalogs(): void
    {
        Sanctum::actingAs($this->seedUser('vendedor'));

        $this->getJson('/api/v1/materiales')->assertForbidden();

        $this->postJson('/api/v1/materiales', [
            'nombre' => 'Material no autorizado',
            'unidad_consumo' => 'unidad',
            'unidad_compra' => 'unidad',
            'precio_actual' => 10,
        ])->assertForbidden();

        $this->getJson('/api/v1/servicios-adicionales')->assertOk();
    }

    private function seedUser(string $role): Usuario
    {
        DB::table('usuarios')->updateOrInsert(
            ['correo' => "catalog-{$role}@example.com"],
            [
                'nombre' => ucfirst($role),
                'password' => Hash::make('secret123'),
                'rol' => $role,
                'state' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return Usuario::where('correo', "catalog-{$role}@example.com")->firstOrFail();
    }
}
