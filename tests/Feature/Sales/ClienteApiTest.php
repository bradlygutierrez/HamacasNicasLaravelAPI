<?php

namespace Tests\Feature\Sales;

use App\Models\Cliente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClienteApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_and_vendor_can_create_clients_with_nullable_unique_email(): void
    {
        $payload = ['nombre' => 'Empresa Norte', 'ruc' => 'J-123', 'telefono' => '8888-0000', 'correo' => 'empresa-norte@example.com', 'direccion' => 'Managua'];

        Sanctum::actingAs($this->user('admin'));
        $this->postJson('/api/v1/clientes', $payload)->assertCreated()->assertJsonPath('data.nombre', 'Empresa Norte');

        Sanctum::actingAs($this->user('vendedor'));
        $this->postJson('/api/v1/clientes', ['nombre' => 'Cliente Vendedor'])->assertCreated();
    }

    public function test_roles_are_enforced_and_inactive_clients_are_hidden_from_index(): void
    {
        $inactive = Cliente::create(['nombre' => 'Cliente inactivo', 'state' => false]);

        Sanctum::actingAs($this->user('socio'));
        $this->getJson('/api/v1/clientes')->assertOk()->assertJsonMissing(['id' => $inactive->id]);
        $this->postJson('/api/v1/clientes', ['nombre' => 'No permitido'])->assertForbidden();

        Sanctum::actingAs($this->user('almacenista'));
        $this->getJson('/api/v1/clientes')->assertForbidden();
    }

    public function test_duplicate_email_is_rejected_and_client_can_be_updated_and_deactivated(): void
    {
        $admin = $this->user('admin');
        $client = Cliente::create(['nombre' => 'Cliente Original', 'correo' => 'unico@example.com', 'state' => true]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/clientes', ['nombre' => 'Duplicado', 'correo' => 'unico@example.com'])->assertStatus(422);
        $this->putJson("/api/v1/clientes/{$client->id}", ['nombre' => 'Cliente Actualizado', 'correo' => 'actualizado@example.com'])->assertOk()->assertJsonPath('data.nombre', 'Cliente Actualizado');
        $this->deleteJson("/api/v1/clientes/{$client->id}")->assertOk();
        $this->assertDatabaseHas('clientes', ['id' => $client->id, 'state' => false]);
        $this->assertDatabaseHas('clientes', ['id' => $client->id, 'nombre' => 'Cliente Actualizado']);
    }

    public function test_vendor_can_edit_but_cannot_deactivate_and_email_can_be_null(): void
    {
        $client = Cliente::create(['nombre' => 'Cliente Vendedor', 'correo' => null, 'state' => true]);
        Sanctum::actingAs($this->user('vendedor'));

        $this->putJson("/api/v1/clientes/{$client->id}", ['nombre' => 'Cliente Editado', 'correo' => null])->assertOk();
        $this->deleteJson("/api/v1/clientes/{$client->id}")->assertForbidden();
    }

    private function user(string $role): Usuario
    {
        DB::table('usuarios')->updateOrInsert(
            ['correo' => "clientes-{$role}@example.com"],
            ['nombre' => ucfirst($role), 'password' => Hash::make('secret123'), 'rol' => $role, 'state' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        return Usuario::where('correo', "clientes-{$role}@example.com")->firstOrFail();
    }
}
