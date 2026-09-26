<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class HamacaNameService
{
    public function suggest(int $categoriaId, int $tamanoId, array $colorIds): string
    {
        $category = DB::table('categorias')->where('id', $categoriaId)->value('nombre');
        $size = DB::table('tamanos')->where('id', $tamanoId)->value('nombre');
        $base = trim(implode(' ', array_filter([$category, $size])));
        $colors = DB::table('colores')->whereIn('id', $colorIds)->orderBy('nombre')->pluck('nombre')->all();

        return $colors === [] ? $base : $base . ' - ' . implode(' / ', $colors);
    }
}
