<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Hamaca;
use App\Models\Usuario;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Movimiento>
 */
class MovimientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
           'hamaca_id' => \App\Models\Hamaca::inRandomOrder()->first()->id,
            'usuario_id' => \App\Models\Usuario::inRandomOrder()->first()->id,
            'tipo' => $this->faker->randomElement(['entrada', 'salida']),
            'cantidad' => $this->faker->numberBetween(1, 10),
            'fecha' => $this->faker->date(),
        ];
    }
}
