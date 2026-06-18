<?php

namespace Tests\Feature\Inventory;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MovimientoApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inventory_manager_can_create_update_and_delete_movimiento(): void
    {
        $admin = $this->seedUser('admin');
        $seed = $this->seedInventory($admin->id);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/movimientos', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'usuario_id' => $admin->id,
            'ubicacion_destino_id' => $seed['ubicacion_id'],
            'tipo' => 'entrada',
            'cantidad' => 3,
            'fecha' => '2026-06-18 10:00:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Movimiento creado correctamente')
            ->assertJsonPath('data.inventario_hamaca_id', $seed['inventario_id'])
            ->assertJsonPath('data.tipo', 'entrada')
            ->assertJsonPath('data.cantidad', 3);

        $movimientoId = $response->json('data.id');

        $this->putJson("/api/v1/movimientos/{$movimientoId}", [
            'cantidad' => 5,
            'tipo' => 'transferencia',
            'ubicacion_origen_id' => $seed['ubicacion_id'],
            'ubicacion_destino_id' => $seed['ubicacion_id'],
        ])->assertOk()
            ->assertJsonPath('data.cantidad', 5)
            ->assertJsonPath('data.tipo', 'transferencia');

        $this->deleteJson("/api/v1/movimientos/{$movimientoId}")
            ->assertOk()
            ->assertJsonPath('message', 'Movimiento eliminado correctamente');

        $this->assertDatabaseMissing('movimientos', [
            'id' => $movimientoId,
        ]);
    }

    private function seedUser(string $role): Usuario
    {
        DB::table('usuarios')->updateOrInsert(
            ['correo' => "{$role}@example.com"],
            [
                'nombre' => ucfirst($role),
                'password' => Hash::make('secret123'),
                'rol' => $role,
                'state' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return Usuario::where('correo', "{$role}@example.com")->firstOrFail();
    }

    private function seedInventory(int $usuarioId): array
    {
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Familiar',
            'descripcion' => 'Familiar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tamanoId = DB::table('tamanos')->insertGetId([
            'nombre' => 'Grande',
            'descripcion' => 'Grande',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ubicacionId = DB::table('ubicaciones')->insertGetId([
            'nombre' => 'Mercado',
            'descripcion' => 'Mercado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hamacaId = DB::table('hamacas')->insertGetId([
            'nombre' => 'Familiar Blanca',
            'descripcion' => 'Modelo',
            'categoria_id' => $categoriaId,
            'tamano_id' => $tamanoId,
            'precio' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inventarioId = DB::table('inventario_hamacas')->insertGetId([
            'hamaca_id' => $hamacaId,
            'usuario_id' => $usuarioId,
            'ubicacion_id' => $ubicacionId,
            'composicion_clave' => hash('sha256', 'movimiento-test'),
            'cantidad' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'inventario_id' => $inventarioId,
            'ubicacion_id' => $ubicacionId,
        ];
    }
}
