<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiKeyMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    public function test_protected_sanctum_route_requires_configured_api_key(): void
    {
        config(['services.hamacas.api_key' => 'testing-api-key']);

        $usuario = $this->seedUser('admin');
        Sanctum::actingAs($usuario);

        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'API key inválida o ausente.');

        $this->getJson('/api/v1/me', ['X-API-Key' => 'wrong-key'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'API key inválida o ausente.');

        $this->getJson('/api/v1/me', ['X-API-Key' => 'testing-api-key'])
            ->assertOk()
            ->assertJsonPath('data.correo', 'admin@example.com');
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
