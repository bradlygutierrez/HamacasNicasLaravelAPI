<?php

namespace Database\Factories;
use App\Models\Usuario;


use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Usuario>
 */
class UsuarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Usuario::class;

    public function definition()
    {
        return [
            'nombre' => $this->faker->name(),
            'correo' => $this->faker->unique()->safeEmail(), // <- cambiar 'email' por 'correo'
            'password' => bcrypt('password123'),
            'rol' => 'socio',
            'state' => true,
        ];
    }
}
