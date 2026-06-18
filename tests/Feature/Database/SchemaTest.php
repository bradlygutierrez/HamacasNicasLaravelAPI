<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    public function test_domain_schema_is_reproducible_without_legacy_tables(): void
    {
        foreach ([
            'usuarios',
            'categorias',
            'tamanos',
            'ubicaciones',
            'colores',
            'hamacas',
            'fotos',
            'hamaca_foto',
            'inventario_hamacas',
            'inventario_hamaca_color',
            'clientes',
            'facturas',
            'detalle_facturas',
            'movimientos',
            'pantallas',
            'permisos',
            'pantalla_permiso_roles',
            'personal_access_tokens',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        foreach (['users', 'usuario_hamaca', 'hamaca_color', 'hamaca_fotos'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Legacy table still exists: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('usuarios', [
            'nombre',
            'correo',
            'password',
            'rol',
            'state',
        ]));

        $this->assertTrue(Schema::hasColumns('inventario_hamacas', [
            'hamaca_id',
            'usuario_id',
            'ubicacion_id',
            'cantidad',
            'composicion_clave',
        ]));

        $this->assertTrue(Schema::hasColumns('facturas', [
            'numero',
            'vendedor_id',
            'cliente_id',
            'subtotal',
            'descuento',
            'tasa_iva',
            'monto_iva',
            'aplica_ir',
            'tasa_ir',
            'monto_ir',
            'total',
        ]));

        $this->assertTrue(Schema::hasColumns('pantallas', [
            'nombre',
            'slug',
            'ruta',
            'icono',
            'orden',
            'state',
        ]));

        $this->assertTrue(Schema::hasColumns('permisos', [
            'nombre',
            'slug',
            'descripcion',
        ]));

        $this->assertTrue(Schema::hasColumns('pantalla_permiso_roles', [
            'pantalla_id',
            'permiso_id',
            'rol',
        ]));
    }

    public function test_inventory_has_required_foreign_keys_and_group_uniqueness(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('inventario_hamacas'));

        $this->assertTrue($foreignKeys->contains(fn (array $foreignKey): bool => $foreignKey['foreign_table'] === 'hamacas'));
        $this->assertTrue($foreignKeys->contains(fn (array $foreignKey): bool => $foreignKey['foreign_table'] === 'usuarios'));
        $this->assertTrue($foreignKeys->contains(fn (array $foreignKey): bool => $foreignKey['foreign_table'] === 'ubicaciones'));

        $indexes = collect(Schema::getIndexes('inventario_hamacas'));

        $this->assertTrue($indexes->contains(
            fn (array $index): bool => $index['unique'] === true
                && $index['primary'] === false
                && in_array('hamaca_id', $index['columns'], true)
                && in_array('usuario_id', $index['columns'], true)
                && in_array('ubicacion_id', $index['columns'], true)
                && in_array('composicion_clave', $index['columns'], true)
        ));
    }

    public function test_screen_permission_role_table_has_required_foreign_keys_and_uniqueness(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('pantalla_permiso_roles'));

        $this->assertTrue($foreignKeys->contains(fn (array $foreignKey): bool => $foreignKey['foreign_table'] === 'pantallas'));
        $this->assertTrue($foreignKeys->contains(fn (array $foreignKey): bool => $foreignKey['foreign_table'] === 'permisos'));

        $indexes = collect(Schema::getIndexes('pantalla_permiso_roles'));

        $this->assertTrue($indexes->contains(
            fn (array $index): bool => $index['unique'] === true
                && $index['primary'] === false
                && in_array('pantalla_id', $index['columns'], true)
                && in_array('permiso_id', $index['columns'], true)
                && in_array('rol', $index['columns'], true)
        ));
    }
}
