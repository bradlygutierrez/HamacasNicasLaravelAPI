<?php

namespace Tests\Feature\Catalog;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hamacas_list_is_public(): void
    {
        $this->seedBaseCatalog();

        $this->getJson('/api/v1/hamacas')
            ->assertOk();
    }

    public function test_photo_can_be_shared_between_multiple_hamacas(): void
    {
        $hamacas = $this->seedBaseCatalog();
        $admin = $this->seedAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/fotos', [
            'ruta' => 'fotos/hamaca-compartida.jpg',
            'hamaca_ids' => $hamacas,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.hamacas.0.id', $hamacas[0])
            ->assertJsonPath('data.hamacas.1.id', $hamacas[1]);

        $fotoId = $response->json('data.id');

        $this->getJson("/api/v1/fotos/{$fotoId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.hamacas');
    }

    private function seedAdmin(): Usuario
    {
        DB::table('usuarios')->updateOrInsert(
            ['correo' => 'admin@example.com'],
            [
                'nombre' => 'Admin',
                'password' => Hash::make('secret123'),
                'rol' => 'admin',
                'state' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return Usuario::where('correo', 'admin@example.com')->firstOrFail();
    }

    private function seedBaseCatalog(): array
    {
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Familiar',
            'descripcion' => 'Modelos familiares',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tamanoId = DB::table('tamanos')->insertGetId([
            'nombre' => 'Grande',
            'descripcion' => 'Tamaño grande',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hamaca1 = DB::table('hamacas')->insertGetId([
            'nombre' => 'Familiar Blanca A',
            'descripcion' => 'Modelo familiar blanco',
            'categoria_id' => $categoriaId,
            'tamano_id' => $tamanoId,
            'precio' => 1500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hamaca2 = DB::table('hamacas')->insertGetId([
            'nombre' => 'Familiar Blanca B',
            'descripcion' => 'Modelo familiar blanco',
            'categoria_id' => $categoriaId,
            'tamano_id' => $tamanoId,
            'precio' => 1500.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$hamaca1, $hamaca2];
    }
}
