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

    public function test_movimientos_are_read_only_audit_records(): void
    {
        $admin = $this->seedUser('admin');
        $seed = $this->seedInventory($admin->id);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/inventario/entradas', [
            'hamaca_variante_id' => $seed['variante_id'],
            'usuario_id' => $admin->id,
            'ubicacion_id' => $seed['ubicacion_id'],
            'cantidad' => 3,
            'fecha' => '2026-06-18 10:00:00',
        ])->assertCreated();

        $movimientoId = DB::table('movimientos')->latest('id')->value('id');

        $this->getJson('/api/v1/movimientos')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $movimientoId,
                'tipo' => 'entrada',
                'cantidad' => 3,
            ]);

        $this->getJson("/api/v1/movimientos/{$movimientoId}")
            ->assertOk()
            ->assertJsonPath('data.id', $movimientoId)
            ->assertJsonPath('data.tipo', 'entrada')
            ->assertJsonPath('data.cantidad', 3);

        $this->postJson('/api/v1/movimientos', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'usuario_id' => $admin->id,
            'ubicacion_destino_id' => $seed['ubicacion_id'],
            'tipo' => 'entrada',
            'cantidad' => 3,
            'fecha' => '2026-06-18 10:00:00',
        ])->assertStatus(405);

        $this->putJson("/api/v1/movimientos/{$movimientoId}", [
            'cantidad' => 5,
            'tipo' => 'transferencia',
            'ubicacion_origen_id' => $seed['ubicacion_id'],
            'ubicacion_destino_id' => $seed['ubicacion_id'],
        ])->assertStatus(405);

        $this->deleteJson("/api/v1/movimientos/{$movimientoId}")
            ->assertStatus(405);

        $this->assertDatabaseHas('movimientos', [
            'id' => $movimientoId,
            'tipo' => 'entrada',
            'cantidad' => 3,
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
            'hamaca_variante_id' => null,
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
            'variante_id' => $this->seedVariant($hamacaId),
        ];
    }

    private function seedVariant(int $hamacaId): int
    {
        $colorId = DB::table('colores')->insertGetId([
            'nombre' => 'Movimiento '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $compositionKey = hash('sha256', (string) $colorId);

        $variantId = DB::table('hamaca_variantes')->insertGetId([
            'hamaca_id' => $hamacaId,
            'nombre' => null,
            'composicion_clave' => $compositionKey,
            'state' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('hamaca_variante_color')->insert([
            'hamaca_variante_id' => $variantId,
            'color_id' => $colorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('inventario_hamacas')
            ->where('hamaca_id', $hamacaId)
            ->update([
                'hamaca_variante_id' => $variantId,
                'composicion_clave' => $compositionKey,
            ]);

        return $variantId;
    }
}
