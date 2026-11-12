<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-token" content="{{ session('token') }}">
    <title>{{ $g->codigo_grupo }} — EGAU Chess</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdmin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminGrupos.css') }}">
</head>
<body style="background:var(--fondo,#f4f6f9); font-family:'Inter',sans-serif;">

@php
    $nivelStr = strtolower($g->nivel ?? '');
    $nivelBadge = match(true) {
        in_array($nivelStr, ['peón','peon','básico','basico'])       => ['class' => 'verde',  'label' => $g->nivel],
        in_array($nivelStr, ['caballo','alfil','intermedio'])         => ['class' => 'azul',   'label' => $g->nivel],
        in_array($nivelStr, ['torre','reina','rey','avanzado'])       => ['class' => 'morado', 'label' => $g->nivel],
        default => ['class' => 'azul', 'label' => $g->nivel ?: 'N/A'],
    };
    $diasMap = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
@endphp

<div style="max-width:1100px; margin:0 auto; padding:32px 24px;">

    {{-- ── Encabezado ── --}}
    <div style="display:flex; align-items:center; gap:16px; margin-bottom:32px; flex-wrap:wrap;">
        <a href="{{ url('/dashboardAdmin') }}"
           style="display:inline-flex; align-items:center; gap:8px; color:var(--texto-suave);
                  text-decoration:none; font-size:14px; padding:8px 14px;
                  border:1px solid var(--borde); border-radius:8px; background:var(--blanco);
                  transition:color 0.2s;"
           onmouseover="this.style.color='var(--naranja)'"
           onmouseout="this.style.color='var(--texto-suave)'">
            <i class="ri-arrow-left-line"></i> Regresar
        </a>
        <div style="flex:1;">
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <h1 style="font-family:'Cinzel',serif; font-size:22px; color:var(--texto); margin:0;">
                    {{ $g->codigo_grupo }}
                </h1>
                <span class="badge {{ $nivelBadge['class'] }}">{{ $nivelBadge['label'] }}</span>
                @if($g->estatus)
                    <span style="font-size:12px; font-weight:600; color:#1e8e3e;">
                        <i class="ri-checkbox-circle-fill"></i> Activo
                    </span>
                @else
                    <span style="font-size:12px; font-weight:600; color:var(--texto-suave);">
                        <i class="ri-close-circle-fill"></i> Inactivo
                    </span>
                @endif
            </div>
            <p style="margin:4px 0 0; font-size:13px; color:var(--texto-suave);">
                {{ $g->nombre_curso }} — Periodo {{ $g->periodo }}
            </p>
        </div>
    </div>

    {{-- ── Información del grupo ── --}}
    <div class="card" style="padding:24px; margin-bottom:24px;">
        <h2 style="font-size:16px; font-weight:600; color:var(--texto);
                   margin:0 0 20px; padding-bottom:12px; border-bottom:1px solid var(--borde);">
            <i class="ri-information-line"></i> Información del Grupo
        </h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:20px;">
            @php
                $campos = [
                    ['Código',          $g->codigo_grupo],
                    ['Profesor',        trim($g->prof_nombre.' '.$g->prof_apellido_p.' '.($g->prof_apellido_m??''))],
                    ['Sede',            $g->nombre_sede ?? '—'],
                    ['Inscritos / Cupo',$inscritos.' / '.$g->cupo_maximo],
                    ['Fecha inicio',    \Carbon\Carbon::parse($g->fecha_inicio)->format('d/m/Y')],
                    ['Fecha fin',       \Carbon\Carbon::parse($g->fecha_fin)->format('d/m/Y')],
                ];
            @endphp
            @foreach($campos as [$etiqueta, $valor])
            <div>
                <p style="font-size:12px; color:var(--texto-suave); margin:0 0 4px;">{{ $etiqueta }}</p>
                <p style="font-size:15px; font-weight:600; color:var(--texto); margin:0;">{{ $valor }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── Horarios ── --}}
    <div class="card" style="padding:24px; margin-bottom:24px;">
        <h2 style="font-size:16px; font-weight:600; color:var(--texto);
                   margin:0 0 20px; padding-bottom:12px; border-bottom:1px solid var(--borde);">
            <i class="ri-calendar-todo-fill"></i> Horarios
        </h2>
        @if($horarios->count())
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#fafafa; border-bottom:1px solid var(--borde);">
                        @foreach(['Día','Hora inicio','Hora fin','Salón / Ubicación'] as $th)
                        <th style="padding:12px 16px; text-align:left; font-weight:600;
                                   font-size:13px; color:var(--texto-suave);">{{ $th }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($horarios as $h)
                    <tr style="border-bottom:1px solid var(--borde);">
                        <td style="padding:14px 16px; color:var(--texto); font-weight:500;">
                            {{ $diasMap[$h->dia_semana] ?? 'Día '.$h->dia_semana }}
                        </td>
                        <td style="padding:14px 16px; color:var(--texto);">{{ substr($h->hora_inicio,0,5) }}</td>
                        <td style="padding:14px 16px; color:var(--texto);">{{ substr($h->hora_fin,0,5) }}</td>
                        <td style="padding:14px 16px; color:var(--texto);">{{ $h->ubicacion ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <p style="color:var(--texto-suave); font-size:14px; margin:0;">Sin horarios registrados.</p>
        @endif
    </div>

    {{-- ── Alumnos inscritos ── --}}
    <div class="card" style="padding:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center;
                    margin-bottom:20px; padding-bottom:12px; border-bottom:1px solid var(--borde);
                    flex-wrap:wrap; gap:12px;">
            <h2 style="font-size:16px; font-weight:600; color:var(--texto); margin:0;">
                <i class="ri-group-line"></i> Alumnos Inscritos
                <span style="font-size:13px; font-weight:400; color:var(--texto-suave); margin-left:8px;">
                    {{ $alumnos->count() }} registros
                </span>
            </h2>
            @if($alumnos->count())
            <div class="search-bar" style="max-width:300px; width:100%; margin:0;">
                <i class="ri-search-line search-icon"></i>
                <input type="text" id="buscador-alumnos" placeholder="Buscar alumno..." autocomplete="off">
            </div>
            @endif
        </div>

        @if($alumnos->count())
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#fafafa; border-bottom:1px solid var(--borde);">
                        @foreach(['#','Nombre completo','Correo','Teléfono','Estatus'] as $th)
                        <th style="padding:12px 16px; text-align:left; font-weight:600;
                                   font-size:13px; color:var(--texto-suave);">{{ $th }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody id="tbody-alumnos">
                    @foreach($alumnos as $i => $alumno)
                    <tr class="fila-alumno" style="border-bottom:1px solid var(--borde);">
                        <td style="padding:14px 16px; color:var(--texto-suave); font-size:13px;">{{ $i + 1 }}</td>
                        <td style="padding:14px 16px; color:var(--texto); font-weight:500;">
                            {{ trim($alumno->nombre.' '.$alumno->apellido_p.' '.($alumno->apellido_m??'')) }}
                        </td>
                        <td style="padding:14px 16px; color:var(--texto);">{{ $alumno->email }}</td>
                        <td style="padding:14px 16px; color:var(--texto);">{{ $alumno->telefono ?? '—' }}</td>
                        <td style="padding:14px 16px;">
                            @if($alumno->estatus_inscripcion)
                                <span style="color:#1e8e3e; font-size:12px; font-weight:600;">
                                    <i class="ri-checkbox-circle-fill"></i> Activo
                                </span>
                            @else
                                <span style="color:var(--texto-suave); font-size:12px; font-weight:600;">
                                    <i class="ri-close-circle-fill"></i> Baja
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p id="msg-vacio" style="display:none; text-align:center; color:var(--texto-suave);
                                  font-size:14px; padding:20px 0;">
            No se encontraron alumnos con ese criterio.
        </p>
        @else
            <p style="color:var(--texto-suave); font-size:14px; text-align:center; padding:16px 0; margin:0;">
                No hay alumnos inscritos en este grupo.
            </p>
        @endif
    </div>

</div>

<script src="{{ asset('animaciones/comun/buscador.js') }}"></script>
<script>
// Los alumnos de un grupo ya están en la página: se filtran aquí, sin acentos y palabra por palabra
const buscador = document.getElementById('buscador-alumnos');
if (buscador) {
    buscador.addEventListener('input', function () {
        const q = this.value.trim();
        const filas = document.querySelectorAll('#tbody-alumnos .fila-alumno');
        let visibles = 0;
        filas.forEach(fila => {
            const coincide = !q || window.EgauBuscador.coincide(fila.textContent, q);
            fila.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });
        const msg = document.getElementById('msg-vacio');
        if (msg) msg.style.display = (visibles === 0 && q) ? 'block' : 'none';
    });
}
</script>

</body>
</html>