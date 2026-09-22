<?php

namespace Tests\Feature\Inventory;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryOwnerApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inventory_owner_catalog_is_available_to_inventory_managers(): void
    {
        $admin = $this->user('admin');
        $socio = $this->user('socio');
        $vendor = $this->user('vendedor');
        $inactiveSocio = $this->user('socio', false);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/usuarios/propietarios')->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($admin->id, $ids);
        $this->assertContains($socio->id, $ids);
        $this->assertNotContains($vendor->id, $ids);
        $this->assertNotContains($inactiveSocio->id, $ids);
    }

    public function test_inventory_owner_catalog_is_forbidden_to_other_roles(): void
    {
        Sanctum::actingAs($this->user('vendedor'));

        $this->getJson('/api/v1/usuarios/propietarios')->assertForbidden();
    }

    private function user(string $role, bool $state = true): Usuario
    {
        return Usuario::create([
            'nombre' => ucfirst($role) . uniqid(),
            'correo' => $role . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'rol' => $role,
            'state' => $state,
        ]);
    }
}
