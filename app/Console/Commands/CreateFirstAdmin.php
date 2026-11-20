<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateFirstAdmin extends Command
{
    protected $signature = 'app:create-first-admin';

    protected $description = 'Create the first administrator from an interactive terminal';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Este comando requiere una terminal interactiva para proteger la contraseña.');

            return self::FAILURE;
        }

        if (Usuario::where('rol', 'admin')->exists()) {
            $this->error('Ya existe un administrador. No se creó ningún usuario.');

            return self::FAILURE;
        }

        $input = [
            'nombre' => trim((string) $this->ask('Nombre')),
            'correo' => trim((string) $this->ask('Correo electrónico')),
            'password' => (string) $this->secret('Contraseña (mínimo 12 caracteres)'),
            'password_confirmation' => (string) $this->secret('Confirma la contraseña'),
        ];

        $validator = Validator::make($input, [
            'nombre' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150', 'unique:usuarios,correo'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        Usuario::create([
            'nombre' => $validated['nombre'],
            'correo' => $validated['correo'],
            'password' => $validated['password'],
            'rol' => 'admin',
            'state' => true,
        ]);

        $this->info('Administrador creado correctamente.');

        return self::SUCCESS;
    }
}
