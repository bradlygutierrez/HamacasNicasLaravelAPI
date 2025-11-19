<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Hamaca;
use App\Models\Color;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HamacaColor>
 */
class HamacaColorFactory extends Factory
{
    // Para almacenar combinaciones ya usadas durante la ejecución
    protected static $usedPairs = [];

    /**
     * Define el estado por defecto del modelo.
     */
    public function definition(): array
    {
        do {
            $hamaca_id = Hamaca::inRandomOrder()->value('id');
            $color_id = Color::inRandomOrder()->value('id');
            $pair = "{$hamaca_id}-{$color_id}";
        } while (in_array($pair, self::$usedPairs));

        self::$usedPairs[] = $pair;

        return [
            'hamaca_id' => $hamaca_id,
            'color_id' => $color_id,
        ];
    }
}
