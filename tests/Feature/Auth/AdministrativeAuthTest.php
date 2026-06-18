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

    public function test_login_issues_sanctum_token_and_hides_password(): void
    {
        $this->seedBasicUser('admin');

        $response = $this->postJson('/api/v1/login', [
            'correo' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'access_token',
                'token_type',
                'user' => [
                    'id',
                    'nombre',
                    'correo',
                    'foto',
                    'rol',
                ],
            ])
            ->assertJsonMissingPath('user.password');
    }

    public function test_me_returns_authenticated_user(): void
    {
        $usuario = $this->seedBasicUser('vendedor');

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.correo', 'vendedor@example.com');
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
