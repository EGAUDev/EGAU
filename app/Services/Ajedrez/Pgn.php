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

        // Comentarios, variantes y NAGs
        $pgn = preg_replace('/\{[^}]*\}/u', ' ', $pgn);
        $pgn = preg_replace('/;[^\n]*/u', ' ', $pgn);
        while (preg_match('/\([^()]*\)/u', $pgn)) {
            $pgn = preg_replace('/\([^()]*\)/u', ' ', $pgn);
        }
        $pgn = preg_replace('/\$\d+/', ' ', $pgn);

        $resultado = $etiquetas['Result'] ?? '*';
        $tokens = [];
        foreach (preg_split('/\s+/', $pgn, -1, PREG_SPLIT_NO_EMPTY) as $tok) {
            if (in_array($tok, ['1-0', '0-1', '1/2-1/2', '*'], true)) { $resultado = $tok; continue; }
            $tok = preg_replace('/^\d+\.(\.\.)?/', '', $tok);   // "12." "12..." pegados a la jugada
            if ($tok === '' || preg_match('/^\.+$/', $tok)) continue;
            $tokens[] = $tok;
        }
        if (!$tokens) {
            throw new InvalidArgumentException('El PGN no tiene jugadas.');
        }
        if (count($tokens) > self::MAX_JUGADAS) {
            throw new InvalidArgumentException('La partida tiene demasiadas jugadas.');
        }

        $espanol = self::pareceEspanol($tokens);
        $pos = Posicion::desdeFen(($etiquetas['SetUp'] ?? '') === '1' && !empty($etiquetas['FEN']) ? $etiquetas['FEN'] : Posicion::INICIAL);
        $fens = [$pos->fen()];
        $jugadas = [];

        foreach ($tokens as $i => $tok) {
            $san = $espanol ? self::aIngles($tok) : $tok;
            $j = $pos->buscarSan($san);
            if ($j === null) {
                $num = $pos->numero . ($pos->turno === 'w' ? '.' : '...');
                throw new InvalidArgumentException("Jugada ilegal o no reconocida: {$num} {$tok} (jugada " . ($i + 1) . ').');
            }
            $jugadas[] = $pos->san($j);
            $pos->aplicar($j);
            $fens[] = $pos->fen();
        }

        return [
            'etiquetas'  => $etiquetas,
            'jugadas'    => $jugadas,
            'jugadas_es' => array_map([self::class, 'aEspanol'], $jugadas),
            'fens'       => $fens,
            'fen_final'  => end($fens),
            'resultado'  => $resultado,
        ];
    }

    /** PGN limpio (sin comentarios) a partir de las jugadas validadas. */
    public static function escribir(array $jugadas, array $etiquetas = [], string $resultado = '*'): string
    {
        $cab = '';
        foreach ($etiquetas as $k => $v) {
            $cab .= '[' . $k . ' "' . addcslashes((string) $v, '"\\') . "\"]\n";
        }
        $cuerpo = [];
        foreach ($jugadas as $i => $san) {
            $cuerpo[] = ($i % 2 === 0 ? (intdiv($i, 2) + 1) . '. ' : '') . $san;
        }
        return ($cab ? $cab . "\n" : '') . trim(implode(' ', $cuerpo) . ' ' . $resultado);
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
