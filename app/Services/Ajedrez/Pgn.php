<?php
namespace App\Services\Ajedrez;

use InvalidArgumentException;

/**
 * Lee y valida un PGN (SCRUM-30): revisa que cada jugada sea legal y calcula la FEN final.
 * Acepta notación en inglés (KQRBN) y en español (RDTAC).
 *
 * Resultado de leer():
 *   etiquetas  ['White' => ..., 'Event' => ...]
 *   jugadas    SAN normalizada en inglés: ['e4', 'e5', 'Nf3', ...]
 *   jugadas_es la misma lista en español: ['e4', 'e5', 'Cf3', ...]
 *   fens       FEN después de cada jugada (fens[0] es la inicial)
 *   fen_final  FEN de la última posición
 *   resultado  '1-0', '0-1', '1/2-1/2' o '*'
 *   variantes  opciones a jugadas de la línea principal (ver linea())
 */
class Pgn
{
    private const ES_A_EN = ['R' => 'K', 'D' => 'Q', 'T' => 'R', 'A' => 'B', 'C' => 'N'];
    private const EN_A_ES = ['K' => 'R', 'Q' => 'D', 'R' => 'T', 'B' => 'A', 'N' => 'C'];
    public const MAX_JUGADAS = 600;

    public static function leer(string $pgn): array
    {
        $pgn = str_replace(["\r\n", "\r", '½-½', '½'], ["\n", "\n", '1/2-1/2', '1/2'], $pgn);
        if (strlen($pgn) > 200_000) {
            throw new InvalidArgumentException('El PGN es demasiado grande.');
        }

        // Etiquetas [Nombre "valor"]
        $etiquetas = [];
        $pgn = preg_replace_callback('/\[\s*(\w+)\s+"((?:[^"\\\\]|\\\\.)*)"\s*\]/u', function ($m) use (&$etiquetas) {
            $etiquetas[$m[1]] = stripslashes($m[2]);
            return ' ';
        }, $pgn);

        // Comentarios y NAGs. Las variantes "( ... )" se leen aparte (SCRUM-37).
        $pgn = preg_replace('/\{[^}]*\}/u', ' ', $pgn);
        $pgn = preg_replace('/;[^\n]*/u', ' ', $pgn);
        $pgn = preg_replace('/\$\d+/', ' ', $pgn);
        $pgn = str_replace(['(', ')'], [' ( ', ' ) '], $pgn);

        $resultado = $etiquetas['Result'] ?? '*';
        $tokens = [];
        foreach (preg_split('/\s+/', $pgn, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
            if (in_array($tok, ['1-0', '0-1', '1/2-1/2', '*'], true)) { $resultado = $tok; continue; }
            $tok = preg_replace('/^\d+\.(\.\.)?/', '', $tok);   // "12." "12..." pegados a la jugada
            $tok = rtrim($tok, '!?');                              // e4!? Cf3?
            if ($tok === '' || preg_match('/^\.+$/', $tok)) continue;
            $tokens[] = $tok;
        }
        $jugadasPrincipales = count(array_filter($tokens, fn($t) => $t !== '(' && $t !== ')'));
        if (!$jugadasPrincipales) {
            throw new InvalidArgumentException('El PGN no tiene jugadas.');
        }
        if ($jugadasPrincipales > self::MAX_JUGADAS * 3) {
            throw new InvalidArgumentException('La partida tiene demasiadas jugadas.');
        }

        $espanol = self::pareceEspanol(array_values(array_filter($tokens, fn($t) => $t !== '(' && $t !== ')')));
        $pos = Posicion::desdeFen(($etiquetas['SetUp'] ?? '') === '1' && !empty($etiquetas['FEN']) ? $etiquetas['FEN'] : Posicion::INICIAL);

        $i = 0;
        $variantes = [];
        [$jugadas, $fens] = self::linea($tokens, $i, $pos, $espanol, 0, $variantes);
        if ($i < count($tokens)) {
            throw new InvalidArgumentException('Hay un paréntesis ")" de más en el PGN.');
        }
        if (count($jugadas) > self::MAX_JUGADAS) {
            throw new InvalidArgumentException('La partida tiene demasiadas jugadas.');
        }

        return [
            'etiquetas'  => $etiquetas,
            'jugadas'    => $jugadas,
            'jugadas_es' => array_map([self::class, 'aEspanol'], $jugadas),
            'fens'       => $fens,
            'fen_final'  => end($fens),
            'resultado'  => $resultado,
            'variantes'  => $variantes,
        ];
    }

    /**
     * Lee una línea de jugadas hasta ")" o el final. Cada "( ... )" es otra opción para la última jugada leída.
     * Las variantes de la línea principal se guardan en $variantes con:
     *   desde  número de la jugada que reemplazan (1 = primera jugada; fens[desde-1] es la posición de partida)
     *   jugadas, jugadas_es, fens (fens[0] = posición antes de la variante)
     * Las variantes dentro de variantes se validan pero no se guardan.
     */
    private static function linea(array $tokens, int &$i, Posicion $pos, bool $espanol, int $profundidad, array &$variantes): array
    {
        if ($profundidad > 8) {
            throw new InvalidArgumentException('Hay demasiadas variantes una dentro de otra.');
        }
        $jugadas = [];
        $fens = [$pos->fen()];
        $antes = null;   // posición antes de la última jugada

        while ($i < count($tokens)) {
            $tok = $tokens[$i++];
            if ($tok === ')') {
                if ($profundidad === 0) { $i--; break; }
                return [$jugadas, $fens];
            }
            if ($tok === '(') {
                if ($antes === null) {
                    throw new InvalidArgumentException('Hay una variante "(" antes de la primera jugada.');
                }
                [$vj, $vf] = self::linea($tokens, $i, clone $antes, $espanol, $profundidad + 1, $variantes);
                if ($profundidad === 0 && $vj && count($variantes) < 300) {
                    $variantes[] = ['desde' => count($jugadas), 'jugadas' => $vj,
                        'jugadas_es' => array_map([self::class, 'aEspanol'], $vj), 'fens' => $vf];
                }
                continue;
            }
            $san = $espanol ? self::aIngles($tok) : $tok;
            $j = $pos->buscarSan($san);
            if ($j === null) {
                $num = $pos->numero . ($pos->turno === 'w' ? '.' : '...');
                $donde = $profundidad ? ' en una variante' : ' (jugada ' . (count($jugadas) + 1) . ')';
                throw new InvalidArgumentException("Jugada ilegal o no reconocida: {$num} {$tok}{$donde}.");
            }
            $antes = clone $pos;
            $jugadas[] = $pos->san($j);
            $pos->aplicar($j);
            $fens[] = $pos->fen();
        }
        if ($profundidad > 0) {
            throw new InvalidArgumentException('Falta cerrar una variante con ")".');
        }
        return [$jugadas, $fens];
    }

    /**
     * PGN limpio (sin comentarios) a partir de las jugadas validadas.
     * $variantes (opcional) con el formato de leer(); $fenInicial para partidas que no empiezan desde el inicio.
     */
    public static function escribir(array $jugadas, array $etiquetas = [], string $resultado = '*', array $variantes = [], ?string $fenInicial = null): string
    {
        $cab = '';
        foreach ($etiquetas as $k => $v) {
            $cab .= '[' . $k . ' "' . addcslashes((string) $v, '"\\') . "\"]\n";
        }
        $base = 0;   // medias jugadas antes de la primera (para numerar bien desde una FEN)
        if ($fenInicial) {
            $c = explode(' ', $fenInicial);
            $base = (max(1, (int) ($c[5] ?? 1)) - 1) * 2 + (($c[1] ?? 'w') === 'b' ? 1 : 0);
        }
        $porJugada = [];
        foreach ($variantes as $v) {
            $porJugada[(int) $v['desde']][] = $v['jugadas'];
        }
        $cuerpo = self::numerar($jugadas, $base);
        foreach ($cuerpo as $k => &$texto) {
            foreach ($porJugada[$k + 1] ?? [] as $vj) {
                $texto .= ' (' . implode(' ', self::numerar($vj, $base + $k, true)) . ')';
                if (isset($cuerpo[$k + 1]) && ($base + $k + 1) % 2 === 1) {
                    $cuerpo[$k + 1] = (intdiv($base + $k + 1, 2) + 1) . '... ' . $jugadas[$k + 1];
                }
            }
        }
        unset($texto);
        return ($cab ? $cab . "\n" : '') . trim(implode(' ', $cuerpo) . ' ' . $resultado);
    }

    /** "1. e4", "e5", "2. Cf3"... $ply = medias jugadas ya hechas; $forzar pone "N..." si empieza con negras. */
    private static function numerar(array $jugadas, int $ply, bool $forzar = false): array
    {
        $out = [];
        foreach ($jugadas as $i => $san) {
            $p = $ply + $i;
            $num = intdiv($p, 2) + 1;
            $out[] = $p % 2 === 0 ? "{$num}. {$san}" : (($i === 0 && ($forzar || $ply > 0)) ? "{$num}... {$san}" : $san);
        }
        return $out;
    }

    public static function aEspanol(string $san): string
    {
        return preg_replace_callback('/(^[KQRBN])|(=[QRBN])/', fn($m) => $m[1] !== ''
            ? self::EN_A_ES[$m[1]] : '=' . self::EN_A_ES[$m[2][1]], $san);
    }

    public static function aIngles(string $san): string
    {
        return preg_replace_callback('/(^[RDTAC])|(=?[DTAC](?=[+#!?]*$))/', function ($m) {
            if ($m[1] !== '') return self::ES_A_EN[$m[1]];
            return (str_starts_with($m[2], '=') ? '=' : '') . self::ES_A_EN[ltrim($m[2], '=')];
        }, $san);
    }

    /** Español si alguna jugada usa D, T, A o C como pieza (R sola es ambigua: rey o torre). */
    private static function pareceEspanol(array $tokens): bool
    {
        foreach ($tokens as $t) {
            if (preg_match('/^[DTAC]/', $t) || preg_match('/=[DTAC]/', $t)) return true;
            if (preg_match('/^[QNB]/', $t) || preg_match('/=[QNB]/', $t)) return false;
        }
        return false;
    }
}
