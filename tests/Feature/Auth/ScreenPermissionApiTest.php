<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScreenPermissionApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_manage_screens_permissions_and_role_assignments(): void
    {
        $admin = $this->seedUser('admin');
        Sanctum::actingAs($admin);

        $screenSlug = 'ventas-pos-'.uniqid();
        $permissionSlug = 'ver-'.uniqid();
        $pantallaId = $this->postJson('/api/v1/pantallas', [
            'nombre' => 'Ventas POS',
            'slug' => $screenSlug,
            'descripcion' => 'Pantalla para registrar ventas POS.',
            'ruta' => '/ventas',
            'icono' => 'shopping-cart',
            'orden' => 10,
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', $screenSlug)
            ->json('data.id');

        $permisoId = $this->postJson('/api/v1/permisos', [
            'nombre' => 'Ver',
            'slug' => $permissionSlug,
            'descripcion' => 'Permite ver una pantalla.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', $permissionSlug)
            ->json('data.id');

        $this->postJson('/api/v1/pantalla-permiso-roles', [
            'pantalla_id' => $pantallaId,
            'permiso_id' => $permisoId,
            'rol' => 'vendedor',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rol', 'vendedor')
            ->assertJsonPath('data.pantalla.slug', $screenSlug)
            ->assertJsonPath('data.permiso.slug', $permissionSlug);

        $this->assertDatabaseHas('pantalla_permiso_roles', [
            'pantalla_id' => $pantallaId,
            'permiso_id' => $permisoId,
            'rol' => 'vendedor',
        ]);
    }

    public function test_current_role_access_returns_only_authenticated_users_role_assignments(): void
    {
        $admin = $this->seedUser('admin');
        $vendedor = $this->seedUser('vendedor');
        Sanctum::actingAs($admin);

        $screenSlug = 'facturas-'.uniqid();
        $permissionSlug = 'ver-'.uniqid();
        $pantallaId = $this->postJson('/api/v1/pantallas', [
            'nombre' => 'Facturas',
            'slug' => $screenSlug,
            'ruta' => '/facturas',
        ])->json('data.id');

        $permisoId = $this->postJson('/api/v1/permisos', [
            'nombre' => 'Ver',
            'slug' => $permissionSlug,
        ])->json('data.id');

        $this->postJson('/api/v1/pantalla-permiso-roles', [
            'pantalla_id' => $pantallaId,
            'permiso_id' => $permisoId,
            'rol' => 'vendedor',
        ])->assertCreated();

        Sanctum::actingAs($vendedor);

        $response = $this->getJson('/api/v1/pantalla-permiso-roles/current')->assertOk();
        $match = collect($response->json('data'))->first(fn ($row) => $row['pantalla']['slug'] === $screenSlug);
        $this->assertSame('vendedor', $match['rol']);
        $this->assertSame($permissionSlug, $match['permiso']['slug']);
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
}
