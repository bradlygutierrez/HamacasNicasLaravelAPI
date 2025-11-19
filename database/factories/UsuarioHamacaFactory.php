<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Usuario;
use App\Models\Hamaca;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UsuarioHamaca>
 */
class UsuarioHamacaFactory extends Factory
{
    protected static $usedPairs = [];

    public function definition(): array
    {
        do {
            $usuario_id = Usuario::inRandomOrder()->value('id');
            $hamaca_id = Hamaca::inRandomOrder()->value('id');
            $pair = "{$usuario_id}-{$hamaca_id}";
        } while (in_array($pair, self::$usedPairs));

        self::$usedPairs[] = $pair;

        return [
            'usuario_id' => $usuario_id,
            'hamaca_id' => $hamaca_id,
        ];
    }
}
