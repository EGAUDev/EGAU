/**
 * dashboardAdminNiveles.js
 * EGAU Chess — Sección Niveles de Estudiantes
 *
 * calcularRango() es copia exacta de dashboardAlumnoMiNivel.js — NO modificar.
 */
(function () {
    'use strict';

    // ── calcularRango — copia exacta del JS del alumno ───────────────
    function calcularRango(puntos) {
        if (puntos >= 3000) return { numero: 6, nombre: 'Rey', emoji: '♚', animClass: 'anim-rey' };
        if (puntos >= 1500) return { numero: 5, nombre: 'Reina', emoji: '♛', animClass: 'anim-reina' };
        if (puntos >= 800) return { numero: 4, nombre: 'Torre', emoji: '♜', animClass: 'anim-torre' };
        if (puntos >= 400) return { numero: 3, nombre: 'Alfil', emoji: '♝', animClass: 'anim-alfil' };
        if (puntos >= 150) return { numero: 2, nombre: 'Caballo', emoji: '♞', animClass: 'anim-caballo' };
        return { numero: 1, nombre: 'Peón', emoji: '♟', animClass: 'anim-peon' };
    }

    // ── Config ───────────────────────────────────────────────────────
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const PERMISOS = window.PERMISOS_NIVELES || { ver: false, edit: false, admin: false };

    const $ = id => document.getElementById(id);
    const qs = (sel, ctx = document) => ctx.querySelector(sel);

    // ── Estado ───────────────────────────────────────────────────────
    const state = {
        alumnos: [],
        filtros: { q: '', id_sede: '', id_grupo: '', nivel: '' },
        paginaActual: 1,
        porPagina: 15,
        // modal edición
        idAlumnoEditar: null,
        nombreAlumnoEditar: '',
        puntajeActual: 0,
    };

    // ── Init (se llama una sola vez cuando la sección se hace visible) ──
    function init() {
        cargarGrupos();
        cargarAlumnos();
        bindFiltros();
        bindModal();
    }

    // ── Carga de grupos para el dropdown ────────────────────────────
    function cargarGrupos() {
        fetch('/admin/niveles/grupos', { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(r => r.json())
            .then(json => {
                const container = $('niv-grupo-options');
                if (!container) return;
                const grupos = json.data || [];
                grupos.forEach(g => {
                    const div = document.createElement('div');
                    div.className = 'custom-option';
                    div.dataset.value = g.id_grupo;
                    div.textContent = g.codigo_grupo + (g.nombre_curso ? ` — ${g.nombre_curso}` : '');
                    container.appendChild(div);
                });
                // Re-bind con pequeño delay para que el DOM se actualice
                setTimeout(() => {
                    bindDropdownOpts($('dropdown-niv-grupo'), val => {
                        state.filtros.id_grupo = val;
                        state.paginaActual = 1;
                        cargarAlumnos();
                    });
                }, 100);
            })
            .catch(() => { });
    }

    // ── Carga principal de alumnos ───────────────────────────────────
    // Paginada en el servidor; las estadísticas (KPIs) vienen calculadas sobre todo el resultado
    let busqueda = null;
    function cargarAlumnos() {
        mostrarLoading();
        if (!busqueda) {
            busqueda = window.EgauBuscador.crear({
                url: '/admin/niveles/alumnos',
                porPagina: state.porPagina,
                palabra: 'estudiante',
                info: $('niv-pag-info'),
                botones: $('niv-pag-btns'),
                params: () => ({ ...state.filtros }),
                alRecibir: (json) => {
                    state.alumnos = json.data || [];
                    state.paginaActual = json.meta?.page ?? 1;
                    actualizarKpis(json.stats || {});
                    renderTabla();
                },
                alFallar: () => {
                    const tbody = $('tbody-niveles');
                    if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#c0392b;padding:1.5rem;">Error al cargar los datos.</td></tr>';
                },
            });
        }
        busqueda.irA(state.paginaActual);
    }

    function mostrarLoading() {
        const tbody = $('tbody-niveles');
        const cols = PERMISOS.edit ? 6 : 5;
        if (tbody) tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center;padding:2rem;color:var(--texto-suave);"><i class="ri-loader-4-line ri-spin" style="font-size:1.5rem;"></i></td></tr>`;
    }

    // ── KPI Cards ────────────────────────────────────────────────────
    function actualizarKpis(stats) {
        setText('kpi-total', stats.total ?? '—');
        setText('kpi-prom-pts', stats.prom_puntos ?? '—');
        setText('kpi-prom-niv', stats.prom_nivel ?? '—');
    }

    // ── Renderizado de tabla ─────────────────────────────────────────
    function renderTabla() {
        const tbody = $('tbody-niveles');
        if (!tbody) return;

        const pagina = state.alumnos;   // el servidor ya manda solo la página actual
        const cols = PERMISOS.edit ? 6 : 5;

        if (pagina.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${cols}" style="text-align:center;padding:2rem;color:var(--texto-suave);">No se encontraron estudiantes.</td></tr>`;
            return;
        }

        tbody.innerHTML = pagina.map(a => {
            const puntos = parseInt(a.puntaje) || 0;
            // El nivel viene del servidor (rangos de academico.nivel); calcularRango queda de respaldo
            const rango = a.nivel_num
                ? { numero: a.nivel_num, nombre: a.nivel_nombre, emoji: a.nivel_emoji }
                : calcularRango(puntos);
            const badgeCls = `badge-nivel badge-nivel-${rango.numero}`;

            return `
            <tr data-id="${a.id_alumno}" data-puntaje="${puntos}" data-nombre="${esc(a.nombre)}">
                <td><strong>${esc(a.nombre)}</strong></td>
                <td>${esc(a.nombre_sede)}</td>
                <td>
                    <span class="puntos-actual">${puntos}</span>
                    <span class="puntos-max">/ pts</span>
                </td>
                <td>
                    <div class="${badgeCls}">
                        <span class="badge-nivel-emoji">${rango.emoji}</span>
                        <span class="badge-nivel-texto">${rango.nombre}</span>
                        <span class="badge-nivel-num">Nv.${rango.numero}</span>
                    </div>
                </td>
                <td>${esc(a.codigo_grupo)}</td>
                ${PERMISOS.edit ? `
                <td class="acciones-puntos">
                    <button class="btn-modificar-puntos btn-niv-edit"
                            data-id="${a.id_alumno}"
                            data-nombre="${esc(a.nombre)}"
                            data-puntaje="${puntos}">
                        <i class="ri-edit-line"></i> Modificar
                    </button>
                </td>` : ''}
            </tr>`;
        }).join('');

        // Listeners botones editar
        tbody.querySelectorAll('.btn-niv-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                abrirModalPuntos(
                    parseInt(btn.dataset.id),
                    btn.dataset.nombre,
                    parseInt(btn.dataset.puntaje)
                );
            });
        });
    }

    // ── Filtros ──────────────────────────────────────────────────────
    function bindFiltros() {
        // Buscador
        const buscador = $('buscador-niveles');
        if (buscador) {
            let t;
            buscador.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(() => {
                    state.filtros.q = buscador.value.trim();
                    state.paginaActual = 1;
                    cargarAlumnos();
                }, 350);
            });
        }

        // Filtro nivel (estático)
        bindDropdownOpts($('dropdown-niv-nivel'), val => {
            state.filtros.nivel = val;
            state.paginaActual = 1;
            cargarAlumnos();
        });

        // Filtro sede (solo admin)
        const ddSede = $('dropdown-niv-sede');
        if (ddSede) {
            bindDropdownOpts(ddSede, val => {
                state.filtros.id_sede = val;
                state.paginaActual = 1;
                cargarAlumnos();
            });
        }

        // Limpiar filtros
        const btnLimpiar = $('btn-limpiar-niveles');
        if (btnLimpiar) btnLimpiar.addEventListener('click', limpiarFiltros);

        // Cerrar dropdowns al clic fuera
        document.addEventListener('click', () => {
            document.querySelectorAll('#section-niveles .custom-dropdown.open')
                .forEach(d => d.classList.remove('open'));
        });
    }

    function limpiarFiltros() {
        state.filtros = { q: '', id_sede: '', id_grupo: '', nivel: '' };
        state.paginaActual = 1;

        const buscador = $('buscador-niveles');
        if (buscador) buscador.value = '';

        document.querySelectorAll('#section-niveles .custom-dropdown').forEach(dd => {
            const first = qs('.custom-option', qs('.custom-options-container', dd));
            if (!first) return;
            qs('.selected-text', dd).textContent = first.textContent.trim();
            qs('.selected-text', dd).dataset.value = first.dataset.value || '';
            dd.querySelectorAll('.custom-option').forEach(o => o.classList.remove('selected'));
            first.classList.add('selected');
        });

        cargarAlumnos();
    }

    function bindDropdownOpts(dd, onChange) {
        if (!dd) return;
        const trigger = qs('.custom-select-trigger', dd);
        const optsCont = qs('.custom-options-container', dd);
        if (!trigger || !optsCont) return;

        // Toggle open
        trigger.addEventListener('click', e => {
            e.stopPropagation();
            const wasOpen = dd.classList.contains('open');
            document.querySelectorAll('#section-niveles .custom-dropdown.open')
                .forEach(d => d.classList.remove('open'));
            if (!wasOpen) dd.classList.add('open');
        });

        // Seleccionar opción (incluye las que se añadan después)
        optsCont.addEventListener('click', e => {
            const opt = e.target.closest('.custom-option');
            if (!opt) return;
            const val = opt.dataset.value || '';
            qs('.selected-text', trigger).textContent = opt.textContent.trim();
            qs('.selected-text', trigger).dataset.value = val;
            optsCont.querySelectorAll('.custom-option').forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            dd.classList.remove('open');
            onChange(val);
        });
    }

    // ── Modal modificar puntaje ──────────────────────────────────────
    function bindModal() {
        const modal = $('modal-modificar-puntos');
        const btnClose = $('modal-close-puntos');
        const btnSumar = $('btn-puntos-sumar');
        const btnRestar = $('btn-puntos-restar');

        if (btnClose) btnClose.addEventListener('click', cerrarModal);
        if (modal) modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });

        if (btnSumar) btnSumar.addEventListener('click', () => aplicarPuntos('sumar'));
        if (btnRestar) btnRestar.addEventListener('click', () => aplicarPuntos('restar'));
    }

    function abrirModalPuntos(idAlumno, nombre, puntaje) {
        state.idAlumnoEditar = idAlumno;
        state.nombreAlumnoEditar = nombre;
        state.puntajeActual = puntaje;

        const modal = $('modal-modificar-puntos');
        setText('puntos-modal-nombre', nombre);
        setText('puntos-modal-actuales', puntaje);
        const input = $('puntos-modal-cantidad');
        if (input) input.value = '';
        if (modal) modal.classList.add('open');
    }

    function cerrarModal() {
        const modal = $('modal-modificar-puntos');
        if (modal) modal.classList.remove('open');
    }

    function aplicarPuntos(operacion) {
        const input = $('puntos-modal-cantidad');
        const cant = parseInt(input?.value || '0');

        if (!cant || cant <= 0) {
            alert('Ingresa una cantidad válida mayor a 0.');
            return;
        }

        fetch(`/admin/niveles/alumnos/${state.idAlumnoEditar}/puntaje`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify({ operacion, cantidad: cant }),
        })
            .then(r => r.json())
            .then(res => {
                if (res.error) { alert(res.error); return; }
                if (res.errors) { alert(Object.values(res.errors)[0][0]); return; }

                cerrarModal();

                // Recargar la página actual: así el nivel, el filtro y los KPIs quedan correctos
                cargarAlumnos();
            })
            .catch(() => alert('Error de conexión al actualizar el puntaje.'));
    }

    // ── Utilidades ───────────────────────────────────────────────────
    function setText(id, val) { const e = $(id); if (e) e.textContent = val; }
    function esc(str) {
        return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Boot: inicializa solo cuando la sección se muestra ───────────
    document.addEventListener('DOMContentLoaded', () => {
        const seccion = $('section-niveles');
        if (!seccion) return;

        let inicializado = false;
        function tryInit() {
            if (!inicializado && seccion.style.display !== 'none') {
                inicializado = true;
                init();
            }
        }
        tryInit();
        const obs = new MutationObserver(tryInit);
        obs.observe(seccion, { attributes: true, attributeFilter: ['style'] });
    });

})();
