<?php
namespace App\Services\Ajedrez;

use InvalidArgumentException;

/**
 * Posición de ajedrez con generación de jugadas legales (enroques, al paso y coronación).
 * Casillas 0..63: a1 = 0, h1 = 7, a8 = 56. Piezas: PNBRQK blancas, pnbrqk negras, null vacía.
 */
class Posicion
{
    public const INICIAL = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

    /** @var array<int, ?string> */
    public array $tablero = [];
    public string $turno = 'w';
    public string $enroques = 'KQkq';
    public ?int $alPaso = null;
    public int $medias = 0;
    public int $numero = 1;

    private const CABALLO = [[1, 2], [2, 1], [2, -1], [1, -2], [-1, -2], [-2, -1], [-2, 1], [-1, 2]];
    private const REY = [[1, 0], [1, 1], [0, 1], [-1, 1], [-1, 0], [-1, -1], [0, -1], [1, -1]];
    private const DIAGONALES = [[1, 1], [1, -1], [-1, 1], [-1, -1]];
    private const RECTAS = [[1, 0], [-1, 0], [0, 1], [0, -1]];

    public static function desdeFen(string $fen = self::INICIAL): self
    {
        $partes = preg_split('/\s+/', trim($fen));
        if (count($partes) < 4) {
            throw new InvalidArgumentException('FEN incompleta.');
        }
        $filas = explode('/', $partes[0]);
        if (count($filas) !== 8) {
            throw new InvalidArgumentException('FEN con filas incorrectas.');
        }
        $p = new self();
        $p->tablero = array_fill(0, 64, null);
        foreach ($filas as $i => $fila) {
            $rank = 7 - $i;
            $file = 0;
            foreach (str_split($fila) as $c) {
                if (ctype_digit($c)) {
                    $file += (int) $c;
                } elseif (strpos('pnbrqkPNBRQK', $c) !== false) {
                    if ($file > 7) throw new InvalidArgumentException('FEN con columnas de más.');
                    $p->tablero[$rank * 8 + $file] = $c;
                    $file++;
                } else {
                    throw new InvalidArgumentException("FEN con carácter inválido: {$c}");
                }
            }
            if ($file !== 8) throw new InvalidArgumentException('FEN con columnas incorrectas.');
        }
        $p->turno = $partes[1] === 'b' ? 'b' : 'w';
        $p->enroques = $partes[2] === '-' ? '' : $partes[2];
        $p->alPaso = $partes[3] === '-' ? null : self::casilla($partes[3]);
        $p->medias = (int) ($partes[4] ?? 0);
        $p->numero = max(1, (int) ($partes[5] ?? 1));
        return $p;
    }

    public function fen(): string
    {
        $filas = [];
        for ($rank = 7; $rank >= 0; $rank--) {
            $f = '';
            $vacias = 0;
            for ($file = 0; $file < 8; $file++) {
                $pz = $this->tablero[$rank * 8 + $file];
                if ($pz === null) { $vacias++; continue; }
                if ($vacias) { $f .= $vacias; $vacias = 0; }
                $f .= $pz;
            }
            $filas[] = $f . ($vacias ?: '');
        }
        return implode('/', $filas) . ' ' . $this->turno . ' ' . ($this->enroques ?: '-') . ' '
            . ($this->alPaso === null ? '-' : self::nombre($this->alPaso)) . ' ' . $this->medias . ' ' . $this->numero;
    }

    public static function casilla(string $s): int
    {
        if (!preg_match('/^[a-h][1-8]$/', $s)) throw new InvalidArgumentException("Casilla inválida: {$s}");
        return (ord($s[1]) - 49) * 8 + (ord($s[0]) - 97);
    }

    public static function nombre(int $sq): string
    {
        return chr(97 + $sq % 8) . chr(49 + intdiv($sq, 8));
    }

    private static function color(?string $pz): ?string
    {
        return $pz === null ? null : (ctype_upper($pz) ? 'w' : 'b');
    }

    /** ¿La casilla está atacada por el color dado? */
    public function atacada(int $sq, string $por): bool
    {
        $f = $sq % 8; $r = intdiv($sq, 8);
        $es = fn(int $ff, int $rr, string $tipos) => $ff >= 0 && $ff < 8 && $rr >= 0 && $rr < 8
            && ($pz = $this->tablero[$rr * 8 + $ff]) !== null && self::color($pz) === $por && strpos($tipos, strtolower($pz)) !== false;

        $dir = $por === 'w' ? -1 : 1;   // el peón atacante está una fila "atrás"
        if ($es($f - 1, $r + $dir, 'p') || $es($f + 1, $r + $dir, 'p')) return true;
        foreach (self::CABALLO as [$df, $dr]) if ($es($f + $df, $r + $dr, 'n')) return true;
        foreach (self::REY as [$df, $dr]) if ($es($f + $df, $r + $dr, 'k')) return true;
        foreach ([[self::DIAGONALES, 'bq'], [self::RECTAS, 'rq']] as [$dirs, $tipos]) {
            foreach ($dirs as [$df, $dr]) {
                $ff = $f + $df; $rr = $r + $dr;
                while ($ff >= 0 && $ff < 8 && $rr >= 0 && $rr < 8) {
                    $pz = $this->tablero[$rr * 8 + $ff];
                    if ($pz !== null) {
                        if (self::color($pz) === $por && strpos($tipos, strtolower($pz)) !== false) return true;
                        break;
                    }
                    $ff += $df; $rr += $dr;
                }
            }
        }
        return false;
    }

    public function enJaque(?string $color = null): bool
    {
        $color ??= $this->turno;
        $rey = array_search($color === 'w' ? 'K' : 'k', $this->tablero, true);
        return $rey !== false && $this->atacada($rey, $color === 'w' ? 'b' : 'w');
    }

    /** Jugadas pseudo-legales: [desde, hasta, corona|null, enroque|null] */
    private function pseudo(): array
    {
        $jug = [];
        $yo = $this->turno; $otro = $yo === 'w' ? 'b' : 'w';
        foreach ($this->tablero as $sq => $pz) {
            if ($pz === null || self::color($pz) !== $yo) continue;
            $f = $sq % 8; $r = intdiv($sq, 8); $t = strtolower($pz);
            $dentro = fn($ff, $rr) => $ff >= 0 && $ff < 8 && $rr >= 0 && $rr < 8;

            if ($t === 'p') {
                $d = $yo === 'w' ? 1 : -1; $ini = $yo === 'w' ? 1 : 6; $ult = $yo === 'w' ? 7 : 0;
                $agregar = function (int $hasta) use (&$jug, $sq, $ult) {
                    if (intdiv($hasta, 8) === $ult) {
                        foreach (['q', 'r', 'b', 'n'] as $c) $jug[] = [$sq, $hasta, $c, null];
                    } else {
                        $jug[] = [$sq, $hasta, null, null];
                    }
                };
                if ($dentro($f, $r + $d) && $this->tablero[($r + $d) * 8 + $f] === null) {
                    $agregar(($r + $d) * 8 + $f);
                    if ($r === $ini && $this->tablero[($r + 2 * $d) * 8 + $f] === null) $jug[] = [$sq, ($r + 2 * $d) * 8 + $f, null, null];
                }
                foreach ([-1, 1] as $df) {
                    if (!$dentro($f + $df, $r + $d)) continue;
                    $h = ($r + $d) * 8 + $f + $df;
                    if (self::color($this->tablero[$h]) === $otro || $h === $this->alPaso) $agregar($h);
                }
                continue;
            }
            if ($t === 'n' || $t === 'k') {
                foreach ($t === 'n' ? self::CABALLO : self::REY as [$df, $dr]) {
                    if (!$dentro($f + $df, $r + $dr)) continue;
                    $h = ($r + $dr) * 8 + $f + $df;
                    if (self::color($this->tablero[$h]) !== $yo) $jug[] = [$sq, $h, null, null];
                }
                continue;
            }
            $dirs = $t === 'b' ? self::DIAGONALES : ($t === 'r' ? self::RECTAS : array_merge(self::DIAGONALES, self::RECTAS));
            foreach ($dirs as [$df, $dr]) {
                $ff = $f + $df; $rr = $r + $dr;
                while ($dentro($ff, $rr)) {
                    $h = $rr * 8 + $ff;
                    $c = self::color($this->tablero[$h]);
                    if ($c === $yo) break;
                    $jug[] = [$sq, $h, null, null];
                    if ($c !== null) break;
                    $ff += $df; $rr += $dr;
                }
            }
        }

        // Enroques: el rey no puede estar, pasar ni quedar en jaque
        $base = $yo === 'w' ? 0 : 56;
        $rey = $yo === 'w' ? 'K' : 'k'; $torre = $yo === 'w' ? 'R' : 'r';
        if ($this->tablero[$base + 4] === $rey && !$this->atacada($base + 4, $otro)) {
            if (str_contains($this->enroques, $yo === 'w' ? 'K' : 'k') && $this->tablero[$base + 7] === $torre
                && $this->tablero[$base + 5] === null && $this->tablero[$base + 6] === null
                && !$this->atacada($base + 5, $otro) && !$this->atacada($base + 6, $otro)) {
                $jug[] = [$base + 4, $base + 6, null, 'corto'];
            }
            if (str_contains($this->enroques, $yo === 'w' ? 'Q' : 'q') && $this->tablero[$base] === $torre
                && $this->tablero[$base + 1] === null && $this->tablero[$base + 2] === null && $this->tablero[$base + 3] === null
                && !$this->atacada($base + 3, $otro) && !$this->atacada($base + 2, $otro)) {
                $jug[] = [$base + 4, $base + 2, null, 'largo'];
            }
        }
        return $jug;
    }

    /** Jugadas legales del bando que mueve. */
    public function legales(): array
    {
        return array_values(array_filter($this->pseudo(), function ($j) {
            $copia = clone $this;
            $copia->aplicar($j);
            return !$copia->enJaque($this->turno);
        }));
    }

    /** Aplica una jugada (ya validada) sobre esta posición. */
    public function aplicar(array $j): void
    {
        [$desde, $hasta, $corona, $enroque] = $j;
        $pz = $this->tablero[$desde];
        $t = strtolower($pz);
        $captura = $this->tablero[$hasta] !== null;

        if ($t === 'p' && $hasta === $this->alPaso) {
            $this->tablero[$hasta + ($this->turno === 'w' ? -8 : 8)] = null;
            $captura = true;
        }
        $this->tablero[$hasta] = $corona ? ($this->turno === 'w' ? strtoupper($corona) : $corona) : $pz;
        $this->tablero[$desde] = null;
        if ($enroque) {
            $base = $this->turno === 'w' ? 0 : 56;
            [$tDesde, $tHasta] = $enroque === 'corto' ? [$base + 7, $base + 5] : [$base, $base + 3];
            $this->tablero[$tHasta] = $this->tablero[$tDesde];
            $this->tablero[$tDesde] = null;
        }

        // Derechos de enroque: se pierden si se mueve el rey o una torre, o si capturan la torre
        $quitar = [0 => 'Q', 7 => 'K', 56 => 'q', 63 => 'k'];
        foreach ([$desde, $hasta] as $sq) if (isset($quitar[$sq])) $this->enroques = str_replace($quitar[$sq], '', $this->enroques);
        if ($pz === 'K') $this->enroques = str_replace(['K', 'Q'], '', $this->enroques);
        if ($pz === 'k') $this->enroques = str_replace(['k', 'q'], '', $this->enroques);

        $this->alPaso = ($t === 'p' && abs($hasta - $desde) === 16) ? intdiv($desde + $hasta, 2) : null;
        $this->medias = ($t === 'p' || $captura) ? 0 : $this->medias + 1;
        if ($this->turno === 'b') $this->numero++;
        $this->turno = $this->turno === 'w' ? 'b' : 'w';
    }

    /** Notación algebraica estándar (en inglés) de una jugada legal en esta posición. */
    public function san(array $j, ?array $legales = null): string
    {
        [$desde, $hasta, $corona, $enroque] = $j;
        if ($enroque) {
            $s = $enroque === 'corto' ? 'O-O' : 'O-O-O';
        } else {
            $pz = $this->tablero[$desde];
            $t = strtoupper($pz);
            $captura = $this->tablero[$hasta] !== null || (strtolower($pz) === 'p' && $hasta === $this->alPaso);
            if ($t === 'P') {
                $s = ($captura ? chr(97 + $desde % 8) . 'x' : '') . self::nombre($hasta) . ($corona ? '=' . strtoupper($corona) : '');
            } else {
                $legales ??= $this->legales();
                $otras = array_filter($legales, fn($o) => $o[1] === $hasta && $o[0] !== $desde && $this->tablero[$o[0]] === $pz);
                $dis = '';
                if ($otras) {
                    $mismaCol = array_filter($otras, fn($o) => $o[0] % 8 === $desde % 8);
                    $mismaFila = array_filter($otras, fn($o) => intdiv($o[0], 8) === intdiv($desde, 8));
                    if (!$mismaCol) $dis = chr(97 + $desde % 8);
                    elseif (!$mismaFila) $dis = chr(49 + intdiv($desde, 8));
                    else $dis = self::nombre($desde);
                }
                $s = $t . $dis . ($captura ? 'x' : '') . self::nombre($hasta);
            }
        }
        $copia = clone $this;
        $copia->aplicar($j);
        if ($copia->enJaque()) $s .= $copia->legales() ? '+' : '#';
        return $s;
    }

    /**
     * Busca la jugada legal que corresponde a un texto SAN en inglés (KQRBN).
     * Acepta 0-0, e8Q sin "=", y signos ! ? + # al final.
     */
    public function buscarSan(string $texto): ?array
    {
        $s = preg_replace('/[+#!?]+$/', '', trim($texto));
        $s = str_replace('0', 'O', $s);
        $legales = $this->legales();
        if ($s === 'O-O' || $s === 'O-O-O') {
            foreach ($legales as $j) if ($j[3] === ($s === 'O-O' ? 'corto' : 'largo')) return $j;
            return null;
        }
        if (!preg_match('/^([KQRBN])?([a-h])?([1-8])?x?([a-h][1-8])(?:=?([QRBN]))?$/', $s, $m)) {
            return null;
        }
        $pieza = $m[1] !== '' ? $m[1] : 'P';
        $hasta = self::casilla($m[4]);
        $corona = isset($m[5]) && $m[5] !== '' ? strtolower($m[5]) : null;
        $cand = array_values(array_filter($legales, function ($j) use ($pieza, $hasta, $corona, $m) {
            if ($j[1] !== $hasta || $j[3]) return false;
            if (strtoupper($this->tablero[$j[0]]) !== $pieza) return false;
            if ($m[2] !== '' && $j[0] % 8 !== ord($m[2]) - 97) return false;
            if ($m[3] !== '' && intdiv($j[0], 8) !== (int) $m[3] - 1) return false;
            return $j[2] === $corona;
        }));
        return count($cand) === 1 ? $cand[0] : null;
    }
}
