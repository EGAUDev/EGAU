<?php
namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Niveles de ajedrez leídos de academico.nivel (una sola fuente para los rangos).
 * En la base el nivel 5 se llama "Dama"; la app lo muestra como "Reina".
 */
class Niveles
{
    private const EMOJI = ['peon' => '♟', 'caballo' => '♞', 'alfil' => '♝', 'torre' => '♜', 'reina' => '♛', 'rey' => '♚'];

    /** Lista ordenada: numero, clave, nombre, emoji, min y max (max null en el último). */
    public static function todos(): array
    {
        return Cache::remember('niveles.todos', 600, function () {
            $filas = DB::table('academico.nivel')->orderBy('rating_minimo')->get(['clave', 'nombre', 'rating_minimo']);
            $lista = [];
            foreach ($filas as $i => $n) {
                $clave = $n->clave === 'dama' ? 'reina' : $n->clave;
                $lista[] = [
                    'numero' => $i + 1,
                    'clave'  => $clave,
                    'nombre' => $n->clave === 'dama' ? 'Reina' : $n->nombre,
                    'emoji'  => self::EMOJI[$clave] ?? '',
                    'min'    => (int) $n->rating_minimo,
                    'max'    => isset($filas[$i + 1]) ? (int) $filas[$i + 1]->rating_minimo - 1 : null,
                ];
            }
            return $lista;
        });
    }

    /** Nivel que corresponde a un puntaje. */
    public static function dePuntaje(int $puntos): array
    {
        $nivel = self::todos()[0];
        foreach (self::todos() as $n) {
            if ($puntos >= $n['min']) {
                $nivel = $n;
            }
        }
        return $nivel;
    }

    /** Nivel por clave (peon, caballo...), por nombre (Peón) o por número (1-6). */
    public static function buscar(string|int $claveONumero): ?array
    {
        $claveONumero = self::sinAcentos((string) $claveONumero);
        foreach (self::todos() as $n) {
            if ((string) $n['clave'] === (string) $claveONumero || (string) $n['numero'] === (string) $claveONumero) {
                return $n;
            }
        }
        return null;
    }

    /** Niveles cuyo nombre empieza con la palabra (mínimo 3 letras, sin acentos): "cab" → Caballo. */
    public static function quePorTexto(string $palabra): array
    {
        $palabra = self::sinAcentos($palabra);
        if (mb_strlen($palabra) < 3) {
            return [];
        }
        return array_values(array_filter(self::todos(), fn($n) => str_starts_with(self::sinAcentos($n['nombre']), $palabra)));
    }

    private static function sinAcentos(string $texto): string
    {
        return strtr(mb_strtolower(trim($texto), 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    /** Agrega a la consulta: el puntaje de la columna está dentro del rango del nivel. */
    public static function dentro(Builder $query, string $columna, array $nivel, string $boolean = 'and'): Builder
    {
        return $query->where(function (Builder $w) use ($columna, $nivel) {
            $w->where($columna, '>=', $nivel['min']);
            if ($nivel['max'] !== null) {
                $w->where($columna, '<=', $nivel['max']);
            }
        }, null, null, $boolean);
    }
}
