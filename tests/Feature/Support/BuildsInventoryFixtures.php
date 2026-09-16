<?php

namespace Tests\Feature\Support;

use App\Models\HamacaVariante;
use App\Models\InventarioHamaca;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

trait BuildsInventoryFixtures
{
    protected function userWithRole(string $role, ?string $email = null): Usuario
    {
        $email ??= "{$role}-".uniqid('', true).'@example.com';

        return Usuario::create([
            'nombre' => ucfirst($role),
            'correo' => $email,
            'password' => Hash::make('secret123'),
            'rol' => $role,
            'state' => true,
        ]);
    }

    protected function catalogFixture(array $colorNames = ['Rojo', 'Azul']): array
    {
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Familiar '.uniqid(),
            'descripcion' => 'Categoria de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tamanoId = DB::table('tamanos')->insertGetId([
            'nombre' => 'Grande '.uniqid(),
            'descripcion' => 'Tamano de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hamacaId = DB::table('hamacas')->insertGetId([
            'nombre' => 'Hamaca '.uniqid(),
            'descripcion' => 'Modelo de prueba',
            'categoria_id' => $categoriaId,
            'tamano_id' => $tamanoId,
            'precio' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ubicacionOrigenId = DB::table('ubicaciones')->insertGetId([
            'nombre' => 'Bodega A '.uniqid(),
            'descripcion' => 'Origen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ubicacionDestinoId = DB::table('ubicaciones')->insertGetId([
            'nombre' => 'Bodega B '.uniqid(),
            'descripcion' => 'Destino',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $colorIds = [];

        foreach ($colorNames as $name) {
            $colorIds[] = DB::table('colores')->insertGetId([
                'nombre' => $name.' '.uniqid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        sort($colorIds);
        $compositionKey = hash('sha256', implode(',', $colorIds));

        $variante = HamacaVariante::create([
            'hamaca_id' => $hamacaId,
            'nombre' => 'Variante prueba',
            'composicion_clave' => $compositionKey,
            'state' => true,
        ]);
        $variante->colores()->sync($colorIds);

        return [
            'categoria_id' => $categoriaId,
            'tamano_id' => $tamanoId,
            'hamaca_id' => $hamacaId,
            'ubicacion_origen_id' => $ubicacionOrigenId,
            'ubicacion_destino_id' => $ubicacionDestinoId,
            'color_ids' => $colorIds,
            'composition_key' => $compositionKey,
            'variante_id' => $variante->id,
        ];
    }

    protected function inventoryFixture(
        int $cantidad = 10,
        ?Usuario $propietario = null,
        ?array $catalog = null,
        ?int $ubicacionId = null
    ): array {
        $catalog ??= $this->catalogFixture();
        $propietario ??= $this->userWithRole('socio');

        $inventario = InventarioHamaca::create([
            'hamaca_id' => $catalog['hamaca_id'],
            'hamaca_variante_id' => $catalog['variante_id'],
            'usuario_id' => $propietario->id,
            'ubicacion_id' => $ubicacionId ?? $catalog['ubicacion_origen_id'],
            'composicion_clave' => $catalog['composition_key'],
            'cantidad' => $cantidad,
        ]);

        $inventario->colores()->sync($catalog['color_ids']);

        return [
            ...$catalog,
            'propietario_id' => $propietario->id,
            'inventario_id' => $inventario->id,
        ];
    }
}
