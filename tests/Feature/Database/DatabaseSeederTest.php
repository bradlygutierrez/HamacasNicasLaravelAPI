<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_database_seeder_creates_single_hamaca_products_and_inventory(): void
    {
        $this->seed();

        $hamaca = DB::table('hamacas')->where('nombre', 'Familiar Grande - Blanco / Azul / Rojo / Verde')->whereNull('deleted_at')->first();
        $this->assertNotNull($hamaca);

        $this->assertSame(4, DB::table('hamaca_color')->where('hamaca_id', $hamaca->id)->count());
        $this->assertDatabaseHas('inventario_hamacas', [
            'hamaca_id' => $hamaca->id,
            'cantidad' => 5,
        ]);
        $this->assertSame(1, DB::table('inventario_hamacas')->where('hamaca_id', $hamaca->id)->count());

        foreach (['hamaca_variantes', 'hamaca_variante_color', 'inventario_hamaca_color'] as $legacyTable) {
            $this->assertFalse(DB::getSchemaBuilder()->hasTable($legacyTable), "Unexpected legacy table {$legacyTable}");
        }
        foreach (['hamaca_variante_id', 'composicion_clave'] as $legacyColumn) {
            $this->assertFalse(DB::getSchemaBuilder()->hasColumn('inventario_hamacas', $legacyColumn));
        }
    }
}
