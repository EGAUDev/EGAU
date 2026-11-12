<?php
namespace App\Console\Commands;

use App\Services\BibliotecaEscritura;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * SCRUM-29: importa las aperturas ECO de Lichess (github.com/lichess-org/chess-openings, dominio público CC0)
 * a biblioteca.recurso con tipo "apertura", su código ECO, el PGN validado y la FEN de cada jugada.
 *
 * Es idempotente: cada apertura tiene un slug fijo (eco-c41-philidor-defense). Si ya existe y no cambió,
 * no se toca; si cambió en Lichess, se actualiza. Así se puede correr varias veces sin duplicar.
 *
 *   php artisan biblioteca:importar-eco                 descarga a.tsv ... e.tsv de GitHub
 *   php artisan biblioteca:importar-eco --dir=ruta      usa archivos .tsv ya descargados
 *   php artisan biblioteca:importar-eco --oculto        las deja sin publicar
 */
class ImportarEco extends Command
{
    protected $signature = 'biblioteca:importar-eco {--dir= : carpeta con a.tsv ... e.tsv} {--oculto : importar sin publicar}';
    protected $description = 'Importa las aperturas ECO de Lichess a la biblioteca (se puede correr varias veces)';

    private const URL = 'https://raw.githubusercontent.com/lichess-org/chess-openings/master/%s.tsv';

    public function handle(): int
    {
        $filas = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $letra) {
            $tsv = $this->leerArchivo($letra);
            if ($tsv === null) return self::FAILURE;
            foreach (preg_split('/\r?\n/', trim($tsv)) as $i => $linea) {
                if ($i === 0 || trim($linea) === '') continue;   // encabezado: eco, name, pgn
                $cols = explode("\t", $linea);
                if (count($cols) < 3) continue;
                $filas[] = ['eco' => trim($cols[0]), 'nombre' => trim($cols[1]), 'pgn' => trim($cols[2])];
            }
        }
        $this->info(count($filas) . ' aperturas leídas. Validando jugadas...');

        $nuevas = $actualizadas = $iguales = 0;
        $errores = [];
        $vistos = [];
        $barra = $this->output->createProgressBar(count($filas));

        foreach (array_chunk($filas, 250) as $lote) {
            DB::transaction(function () use ($lote, &$nuevas, &$actualizadas, &$iguales, &$errores, &$vistos, $barra) {
                BibliotecaEscritura::contextoAuditoria('127.0.0.1');
                foreach ($lote as $f) {
                    $barra->advance();
                    if (!preg_match('/^[A-E][0-9]{2}$/', $f['eco'])) {
                        $errores[] = "{$f['eco']} {$f['nombre']}: código ECO inválido";
                        continue;
                    }
                    try {
                        $c = BibliotecaEscritura::contenidoPgn($f['pgn']);
                    } catch (InvalidArgumentException $e) {
                        $errores[] = "{$f['eco']} {$f['nombre']}: {$e->getMessage()}";
                        continue;
                    }
                    unset($c['etiquetas']);

                    $slug = mb_substr('eco-' . strtolower($f['eco']) . '-' . Str::slug($f['nombre']), 0, 200);
                    if (isset($vistos[$slug])) $slug .= '-' . substr(md5($f['pgn']), 0, 6);   // mismo nombre con otra línea
                    $vistos[$slug] = true;

                    $familia = trim(explode(':', $f['nombre'])[0]);
                    $metadatos = [
                        'creador'        => 'Lichess (chess-openings)',
                        'tema'           => 'Aperturas, ' . $familia,
                        'descripcion'    => "{$f['nombre']} ({$f['eco']}): {$c['pgn']}",
                        'apertura'       => $f['nombre'],
                        'palabras_clave' => [$f['eco'], $familia],
                    ];
                    $fila = [
                        'tipo'      => 'apertura',
                        'titulo'    => mb_substr($f['nombre'], 0, 200),
                        'eco'       => $f['eco'],
                        'idioma'    => 'en',
                        'fuente'    => 'lichess-org/chess-openings',
                        'licencia'  => 'CC0-1.0',
                        'contenido' => json_encode($c, JSON_UNESCAPED_UNICODE),
                        'metadatos' => json_encode($metadatos, JSON_UNESCAPED_UNICODE),
                    ];

                    $actual = DB::table('biblioteca.recurso')->where('slug', $slug)
                        ->first(['id', 'titulo', 'eco', DB::raw("contenido->>'pgn' AS pgn")]);
                    if (!$actual) {
                        DB::table('biblioteca.recurso')->insert($fila + ['slug' => $slug, 'publicado' => !$this->option('oculto')]);
                        $nuevas++;
                    } elseif ($actual->pgn !== $c['pgn'] || $actual->titulo !== $fila['titulo'] || trim($actual->eco) !== $f['eco']) {
                        DB::table('biblioteca.recurso')->where('id', $actual->id)->update($fila);
                        $actualizadas++;
                    } else {
                        $iguales++;
                    }
                }
            });
        }
        $barra->finish();
        $this->newLine(2);
        $this->table(['Nuevas', 'Actualizadas', 'Sin cambios', 'Con error'], [[$nuevas, $actualizadas, $iguales, count($errores)]]);
        foreach (array_slice($errores, 0, 20) as $e) $this->warn($e);
        return self::SUCCESS;
    }

    private function leerArchivo(string $letra): ?string
    {
        if ($dir = $this->option('dir')) {
            $ruta = rtrim($dir, '/') . "/{$letra}.tsv";
            if (!is_file($ruta)) {
                $this->error("No existe {$ruta}");
                return null;
            }
            return file_get_contents($ruta);
        }
        $r = Http::timeout(60)->retry(3, 2000)->get(sprintf(self::URL, $letra));
        if (!$r->successful()) {
            $this->error("No se pudo descargar {$letra}.tsv (HTTP {$r->status()}). Usa --dir con los archivos descargados.");
            return null;
        }
        return $r->body();
    }
}
