<?php

namespace Tests\Feature\Auth;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateFirstAdminCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_interactive_command_creates_first_admin_with_hashed_password(): void
    {
        Usuario::where('rol', 'admin')->update(['rol' => 'socio']);
        $email = Str::uuid().'@example.com';

        $this->artisan('app:create-first-admin')
            ->expectsQuestion('Nombre', 'Admin')
            ->expectsQuestion('Correo electrónico', $email)
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'Secure-password-123')
            ->expectsQuestion('Confirma la contraseña', 'Secure-password-123')
            ->expectsOutput('Administrador creado correctamente.')
            ->assertExitCode(0);

        $admin = Usuario::where('correo', $email)->firstOrFail();

        $this->assertSame('admin', $admin->rol);
        $this->assertTrue($admin->state);
        $this->assertTrue(Hash::check('Secure-password-123', $admin->password));
    }

    public function test_command_refuses_to_create_a_second_admin(): void
    {
        $adminCount = Usuario::where('rol', 'admin')->count();
        Usuario::create([
            'nombre' => 'Existing admin',
            'correo' => Str::uuid().'@example.com',
            'password' => 'Secure-password-123',
            'rol' => 'admin',
            'state' => true,
        ]);

        $this->artisan('app:create-first-admin')
            ->expectsOutput('Ya existe un administrador. No se creó ningún usuario.')
            ->assertExitCode(1);

        $this->assertSame($adminCount + 1, Usuario::where('rol', 'admin')->count());
    }

    public function test_command_requires_an_interactive_terminal(): void
    {
        $this->artisan('app:create-first-admin', ['--no-interaction' => true])
            ->expectsOutput('Este comando requiere una terminal interactiva para proteger la contraseña.')
            ->assertExitCode(1);
    }
}
