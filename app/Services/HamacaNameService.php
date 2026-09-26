<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HamacaNameService
{
    public function suggest(int $categoriaId, int $tamanoId, array $colorIds): string
    {
        $category = DB::table('categorias')->where('id', $categoriaId)->value('nombre');
        $size = DB::table('tamanos')->where('id', $tamanoId)->value('nombre');
        $base = trim(implode(' ', array_filter([$category, $size])));
        $colors = DB::table('colores')->whereIn('id', $colorIds)->orderBy('nombre')->pluck('nombre')->all();

        $suggestion = $colors === [] ? $base : $base . ' - ' . implode(' / ', $colors);

        return Str::substr($suggestion, 0, 150);
    }
}
