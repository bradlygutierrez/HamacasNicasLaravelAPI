<?php

namespace Tests\Feature\Catalog;

use App\Models\Usuario;
use App\Models\Foto;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
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

    public function test_hamaca_detail_loads_without_a_direct_colors_relationship(): void
    {
        [$hamacaId] = $this->seedBaseCatalog();

        $this->getJson("/api/v1/hamacas/{$hamacaId}")
            ->assertOk()
            ->assertJsonPath('data.id', $hamacaId);
    }

    public function test_detailed_hamaca_catalog_clamps_page_size_to_one_hundred(): void
    {
        $this->seedBaseCatalog();
        Sanctum::actingAs($this->seedAdmin());

        $this->getJson('/api/v1/hamacas/detalles?per_page=1000')
            ->assertOk()->assertJsonPath('meta.per_page', 100);
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

    public function test_admin_can_create_list_update_and_validate_tamano(): void
    {
        $admin = $this->seedAdmin();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/v1/tamanos', ['nombre' => 'Individual QA', 'descripcion' => '90 x 190 cm'])
            ->assertCreated()
            ->json('data');

        $this->getJson('/api/v1/tamanos')->assertOk()->assertJsonFragment(['nombre' => 'Individual QA']);
        $this->putJson('/api/v1/tamanos/' . $created['id'], ['nombre' => 'Individual actualizado'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Individual actualizado');
        $this->postJson('/api/v1/tamanos', ['descripcion' => 'Falta nombre'])->assertStatus(422);
    }

    public function test_hamaca_creation_and_suggested_name_support_150_unicode_characters(): void
    {
        $admin = $this->seedAdmin();
        $categoriaId = DB::table('categorias')->insertGetId(['nombre' => str_repeat('é', 50), 'created_at' => now(), 'updated_at' => now()]);
        $tamanoId = DB::table('tamanos')->insertGetId(['nombre' => str_repeat('ó', 50), 'created_at' => now(), 'updated_at' => now()]);
        $colorId = DB::table('colores')->insertGetId(['nombre' => str_repeat('ñ', 50), 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/hamacas', [
            'categoria_id' => $categoriaId, 'tamano_id' => $tamanoId,
            'precio' => 1500, 'color_ids' => [$colorId],
        ])->assertCreated()
            ->assertJsonPath('data.nombre', mb_substr(str_repeat('é', 50).' '.str_repeat('ó', 50).' - '.str_repeat('ñ', 50), 0, 150));
    }

    public function test_photo_copy_source_returns_local_image_bytes_and_rejects_missing_or_external_files(): void
    {
        Storage::fake('public');
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('public')->put('fotos/copy-source.png', $contents);
        $local = Foto::create(['ruta' => 'fotos/copy-source.png']);
        $missing = Foto::create(['ruta' => 'fotos/missing.png']);
        $external = Foto::create(['ruta' => 'https://example.com/image.png']);

        $copyResponse = $this->get("/api/v1/fotos/{$local->id}/copy-source")
            ->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertSame($contents, file_get_contents($copyResponse->baseResponse->getFile()->getPathname()));
        $this->getJson("/api/v1/fotos/{$missing->id}/copy-source")->assertNotFound();
        $this->getJson("/api/v1/fotos/{$external->id}/copy-source")->assertUnprocessable();
    }

    public function test_deleting_photo_removes_local_file_but_never_external_url(): void
    {
        $admin = $this->seedAdmin();
        Sanctum::actingAs($admin);
        Storage::fake('public');
        Storage::disk('public')->put('fotos/to-delete.png', 'pixels');
        $local = Foto::create(['ruta' => 'fotos/to-delete.png']);
        $external = Foto::create(['ruta' => 'https://example.com/keep.png']);

        $this->deleteJson("/api/v1/fotos/{$local->id}")->assertOk();
        $this->deleteJson("/api/v1/fotos/{$external->id}")->assertOk();
        Storage::disk('public')->assertMissing('fotos/to-delete.png');
    }

    public function test_replacing_local_photo_file_deletes_old_file_and_stores_new_file(): void
    {
        $admin = $this->seedAdmin();
        Sanctum::actingAs($admin);
        Storage::fake('public');
        Storage::disk('public')->put('fotos/old-photo.png', 'old image bytes');
        $photo = Foto::create(['ruta' => 'fotos/old-photo.png']);

        $this->put("/api/v1/fotos/{$photo->id}", [
            'foto' => UploadedFile::fake()->create('replacement.png', 10, 'image/png'),
        ])->assertOk();

        Storage::disk('public')->assertMissing('fotos/old-photo.png');
        Storage::disk('public')->assertExists($photo->fresh()->ruta);
    }

    public function test_changing_photo_to_external_url_deletes_old_local_file(): void
    {
        $admin = $this->seedAdmin();
        Sanctum::actingAs($admin);
        Storage::fake('public');
        Storage::disk('public')->put('fotos/old-route-photo.png', 'old image bytes');
        $photo = Foto::create(['ruta' => 'fotos/old-route-photo.png']);

        $this->putJson("/api/v1/fotos/{$photo->id}", [
            'ruta' => 'https://example.com/new-photo.png',
        ])->assertOk()->assertJsonPath('data.ruta', 'https://example.com/new-photo.png');

        Storage::disk('public')->assertMissing('fotos/old-route-photo.png');
        $this->assertSame('https://example.com/new-photo.png', $photo->fresh()->ruta);
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
