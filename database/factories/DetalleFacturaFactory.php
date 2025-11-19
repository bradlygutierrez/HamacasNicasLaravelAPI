<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DetalleFactura>
 */
class DetalleFacturaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
             'factura_id' => \App\Models\Factura::inRandomOrder()->first()->id,
            'hamaca_id' => \App\Models\Hamaca::inRandomOrder()->first()->id,
            'cantidad' => $this->faker->numberBetween(1, 5),
            'precio_unitario' => $this->faker->randomFloat(2, 50, 500),
        ];
    }
}
