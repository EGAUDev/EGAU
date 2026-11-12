<?php
namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Búsqueda de texto común para todas las pantallas.
 *
 * - Minúsculas correctas (mb_strtolower) y sin acentos: "jose" encuentra "José".
 * - Cada palabra se busca por separado: "juan perez" encuentra a Juan Pérez
 *   aunque el nombre y el apellido estén en columnas distintas.
 * - % y _ se escapan para que no funcionen como comodines (con ESCAPE '!').
 * - Paginación opcional en el servidor (?page=&per_page=).
 *
 * Sin acentos usa core.f_sin_acentos() (script 01-busquedas.sql). Si la función
 * todavía no existe en la base, se busca solo con lower() y la app sigue funcionando.
 */
class Buscador
{
    public const POR_PAGINA = 25;
    public const MAX_POR_PAGINA = 100;

    /** Texto de búsqueda limpio: sin espacios de más, en minúsculas y con 100 caracteres como máximo. */
    public static function normalizar(?string $q): string
    {
        $q = preg_replace('/\s+/u', ' ', trim((string) $q));
        return mb_substr(mb_strtolower($q, 'UTF-8'), 0, 100, 'UTF-8');
    }

    /** Las palabras del texto, sin repetidas. */
    public static function palabras(?string $q): array
    {
        $q = self::normalizar($q);
        return $q === '' ? [] : array_values(array_unique(explode(' ', $q)));
    }

    /** Quita acentos del lado de PHP (para comparar con valores fijos, como los nombres de nivel). */
    public static function sinAcentos(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');
        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    /** El texto como queda del lado de la base: sin acentos si existe core.f_sin_acentos(), si no solo en minúsculas. */
    public static function comoEnBase(string $texto): string
    {
        return self::hayFuncionSinAcentos() ? self::sinAcentos($texto) : mb_strtolower($texto, 'UTF-8');
    }

    /**
     * Patrón LIKE '%texto%' con % y _ escapados usando "!" (ESCAPE '!').
     * No se usa "\\" porque PDO lo toma como escape dentro de la comilla y confunde los "?".
     */
    public static function patron(string $palabra): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], self::comoEnBase($palabra)) . '%';
    }

    /** ¿Ya se corrió el script que crea core.f_sin_acentos()? */
    public static function hayFuncionSinAcentos(): bool
    {
        return Cache::remember('buscador.f_sin_acentos', 600, function () {
            return (bool) DB::selectOne("SELECT to_regprocedure('core.f_sin_acentos(text)') IS NOT NULL AS hay")->hay;
        });
    }

    /** Expresión SQL normalizada de una columna. */
    public static function expresion(string $columna): string
    {
        return self::hayFuncionSinAcentos()
            ? "core.f_sin_acentos(({$columna})::text)"
            : "lower(({$columna})::text)";
    }

    /**
     * Agrega la condición de texto: cada palabra debe aparecer en alguna de las columnas.
     *
     * @param array    $columnas columnas SQL donde buscar (ej. 'alumno.nombre')
     * @param callable $extra    opcional: fn(Builder $sub, string $palabra) para agregar
     *                           más ORs por palabra (ej. ID exacto, nivel, estatus)
     */
    public static function filtrar(Builder $query, ?string $q, array $columnas, ?callable $extra = null): Builder
    {
        foreach (self::palabras($q) as $palabra) {
            $patron = self::patron($palabra);
            $query->where(function (Builder $sub) use ($columnas, $patron, $palabra, $extra) {
                foreach ($columnas as $col) {
                    $sub->orWhereRaw(self::expresion($col) . " LIKE ? ESCAPE '!'", [$patron]);
                }
                if ($extra) {
                    $extra($sub, $palabra);
                }
            });
        }
        return $query;
    }

    /** ¿La petición pidió paginación? Sin ?page ni ?per_page se regresa todo, como antes. */
    public static function pidePagina(Request $request): bool
    {
        return $request->filled('page') || $request->filled('per_page');
    }

    /**
     * Ejecuta la consulta, paginada si se pidió.
     * Regresa [filas, meta]; meta trae total, page, per_page y pages.
     */
    public static function obtener(Builder $query, Request $request): array
    {
        if (!self::pidePagina($request)) {
            $filas = $query->get();
            $total = $filas->count();
            return [$filas, ['total' => $total, 'page' => 1, 'per_page' => $total, 'pages' => 1]];
        }

        $porPagina = max(1, min(self::MAX_POR_PAGINA, (int) $request->query('per_page', self::POR_PAGINA)));
        $total = (clone $query)->getCountForPagination();
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = max(1, min($paginas, (int) $request->query('page', 1)));
        $filas = $query->forPage($pagina, $porPagina)->get();

        return [$filas, ['total' => $total, 'page' => $pagina, 'per_page' => $porPagina, 'pages' => $paginas]];
    }
}
