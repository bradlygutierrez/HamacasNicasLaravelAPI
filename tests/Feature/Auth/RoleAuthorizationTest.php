<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_vendor_cannot_create_users(): void
    {
        $usuario = $this->seedUser('vendedor');
        Sanctum::actingAs($usuario);

        $this->postJson('/api/v1/usuarios', [
            'nombre' => 'Nueva Persona',
            'correo' => 'nueva@example.com',
            'password' => 'secret123',
            'rol' => 'socio',
        ])->assertForbidden();
    }

    public function test_admin_can_access_user_management(): void
    {
        $usuario = $this->seedUser('admin');
        Sanctum::actingAs($usuario);

        $this->getJson('/api/v1/usuarios')
            ->assertOk();
    }

    private function seedUser(string $role): Usuario
    {
        DB::table('usuarios')->insert([
            'nombre' => ucfirst($role),
            'correo' => "{$role}@example.com",
            'password' => Hash::make('secret123'),
            'rol' => $role,
            'state' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Usuario::where('correo', "{$role}@example.com")->firstOrFail();
    }
}
