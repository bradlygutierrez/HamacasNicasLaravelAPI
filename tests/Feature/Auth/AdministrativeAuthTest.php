<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdministrativeAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_csrf_endpoint_returns_token_for_stateful_frontend(): void
    {
        $this->withHeader('Origin', 'http://localhost:3000')
            ->getJson('/api/v1/csrf-token')
            ->assertOk()
            ->assertJsonStructure(['csrf_token']);
    }

    public function test_browser_get_to_login_redirects_to_frontend_login(): void
    {
        $this->get('/api/v1/login')
            ->assertRedirect('http://localhost:3000');
    }

    public function test_json_get_to_login_returns_clear_method_error(): void
    {
        $this->getJson('/api/v1/login')
            ->assertStatus(405)
            ->assertJson([
                'message' => 'La ruta de login solo acepta POST.',
            ]);
    }

    public function test_login_starts_http_only_session_and_hides_password(): void
    {
        $this->seedBasicUser('admin');

        $response = $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/v1/login', [
            'correo' => 'admin@example.com',
            'password' => 'secret123',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'nombre',
                    'correo',
                    'foto',
                    'rol',
                ],
            ])
            ->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('user.password');

        $this->getJson('/api/v1/me')->assertOk();
    }

    public function test_me_returns_authenticated_user(): void
    {
        $usuario = $this->seedBasicUser('vendedor');

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.correo', 'vendedor@example.com');
    }

    public function test_non_stateful_client_keeps_bearer_token_compatibility(): void
    {
        $this->seedBasicUser('admin');

        $this->postJson('/api/v1/login', [
            'correo' => 'admin@example.com',
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonStructure(['access_token', 'token_type']);
    }

    public function test_logout_revokes_current_token(): void
    {
        $usuario = $this->seedBasicUser('socio');
        Sanctum::actingAs($usuario);

        $this->postJson('/api/v1/logout')
            ->assertOk();
    }

    private function seedBasicUser(string $role): Usuario
    {
        DB::table('usuarios')->updateOrInsert(
            ['correo' => "{$role}@example.com"],
            [
                'nombre' => strtoupper($role),
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
