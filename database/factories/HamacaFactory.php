<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hamaca>
 */
class HamacaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->word(),
            'descripcion' => $this->faker->sentence(),
            'categoria_id' => 1,
            'ubicacion_id' => 1,
            'tamano_id' => 1,
            'cantidad' => $this->faker->numberBetween(1, 50),
            'precio' => $this->faker->randomFloat(2, 50, 500),
        ];
    }
}
