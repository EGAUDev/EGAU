/**
 * dashboardAdminExtraescolares.js
 * EGAU Chess — Gestión de Actividades Extraescolares
 */
(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const PERMISOS = window.PERMISOS_EXTRAESCOLARES || { ver: false, edit: false, admin: false };

    const $ = id => document.getElementById(id);
    const el = (sel, ctx = document) => ctx.querySelector(sel);

    const state = {
        actividades: [],
        q: '',
    };

    // ── Init ─────────────────────────────────────────────────
    function init() {
        bindBuscador();
        cargarActividades();
        bindModalAgregar();
        bindModalVer();
        bindModalEditar();
    }

    // ── Buscador ─────────────────────────────────────────────
    function bindBuscador() {
        const buscador = $('buscador-extraescolares');
        if (!buscador) return;
        let t;
        buscador.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(() => {
                state.q = buscador.value.trim().toLowerCase();
                renderGrid();
            }, 300);
        });
    }

    // ── Carga de datos ───────────────────────────────────────
    function cargarActividades() {
        const loading = $('extraescolares-loading');
        if (loading) loading.style.display = 'block';

        fetch(`/admin/extraescolares`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(r => r.json())
            .then(json => {
                state.actividades = json.data || [];
                if (loading) loading.style.display = 'none';
                renderGrid();
            })
            .catch(() => {
                if (loading) loading.innerHTML = '<p style="color:#c0392b;">Error al cargar actividades.</p>';
            });
    }

    // ── Renderizado del grid ─────────────────────────────────
    function renderGrid() {
        const grid = $('grid-extraescolares');
        const empty = $('extraescolares-empty');
        if (!grid) return;

        const filtradas = state.actividades.filter(a => {
            if (!state.q) return true;
            return (a.nombre || '').toLowerCase().includes(state.q)
                || (a.ubicacion || '').toLowerCase().includes(state.q)
                || (a.descripcion || '').toLowerCase().includes(state.q);
        });

        if (filtradas.length === 0) {
            grid.innerHTML = '';
            if (empty) empty.style.display = 'block';
            return;
        }

        if (empty) empty.style.display = 'none';

        grid.innerHTML = filtradas.map(a => {
            const esActivo = a.estatus == true || a.estatus == 1;
            const inscMax = a.cupo_maximo ? a.cupo_maximo : null;
            const inscActivos = parseInt(a.inscritos_activos) || 0;
            const pct = inscMax ? Math.min(100, Math.round((inscActivos / inscMax) * 100)) : 0;
            const costo = a.costo_base != null ? `$${parseFloat(a.costo_base).toFixed(2)}` : '—';
            const inicio = a.fecha_inicio ? new Date(a.fecha_inicio).toLocaleDateString('es-MX') : '—';
            const fin = a.fecha_fin ? new Date(a.fecha_fin).toLocaleDateString('es-MX') : '—';

            return `
            <div class="extraescolar-card" data-id="${a.id_extraescolar}">
                <div class="extraescolar-card-header">
                    <h3 class="extraescolar-nombre">${esc(a.nombre)}</h3>
                    <span class="badge ${esActivo ? 'badge-rol-activo' : 'badge-rol-inactivo'}"
                          style="font-size:11px;">
                        ${esActivo ? 'Activo' : 'Inactivo'}
                    </span>
                </div>
                <div class="extraescolar-card-body">
                    ${a.descripcion ? `<div class="extraescolar-info-line"><i class="ri-text-wrap"></i> ${esc(a.descripcion)}</div>` : ''}
                    <div class="extraescolar-inscritos-wrapper">
                        <div class="extraescolar-info-line">
                            <i class="ri-group-line"></i>
                            ${inscActivos}${inscMax ? ` / ${inscMax}` : ''} inscritos
                        </div>
                        ${inscMax ? `
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width:${pct}%;"></div>
                        </div>` : ''}
                    </div>
                    <div class="extraescolar-info-line">
                        <i class="ri-money-dollar-circle-line"></i> Costo: ${costo}
                    </div>
                    <div class="extraescolar-info-line">
                        <i class="ri-calendar-line"></i> ${inicio} / ${fin}
                    </div>
                    ${a.ubicacion ? `<div class="extraescolar-info-line"><i class="ri-map-pin-line"></i> ${esc(a.ubicacion)}</div>` : ''}
                </div>
                <div class="extraescolar-card-footer">
                    <button class="btn-extraescolar-ver btn-ver-extra" data-id="${a.id_extraescolar}" title="Ver">
                        <i class="ri-eye-line"></i> Ver
                    </button>
                    ${PERMISOS.edit ? `
                    <button class="btn-extraescolar-editar btn-editar-extra" data-id="${a.id_extraescolar}">
                        <i class="ri-edit-line"></i> Editar
                    </button>` : ''}
                    ${(PERMISOS.edit && PERMISOS.admin) ? `
                    <button class="btn-extraescolar-eliminar btn-eliminar-extra" data-id="${a.id_extraescolar}">
                        <i class="ri-delete-bin-line"></i>
                    </button>` : ''}
                </div>
            </div>`;
        }).join('');

        grid.querySelectorAll('.btn-ver-extra').forEach(b => b.addEventListener('click', () => abrirVerExtra(+b.dataset.id)));
        grid.querySelectorAll('.btn-editar-extra').forEach(b => b.addEventListener('click', () => abrirEditarExtra(+b.dataset.id)));
        grid.querySelectorAll('.btn-eliminar-extra').forEach(b => b.addEventListener('click', () => eliminarExtra(+b.dataset.id)));
    }

    // ── Modal AGREGAR ────────────────────────────────────────
    function bindModalAgregar() {
        const btnAbrir = $('btn-agregar-extraescolar');
        const btnCancelar = $('btn-cancelar-modal-extraescolar');
        const btnClose = $('modal-close-extraescolar');
        const overlay = $('modal-agregar-extraescolar');
        const form = $('form-agregar-extraescolar');

        if (btnAbrir && PERMISOS.edit) btnAbrir.addEventListener('click', () => { resetFormAgregar(); abrirModal(overlay); });
        if (btnCancelar) btnCancelar.addEventListener('click', () => cerrarModal(overlay));
        if (btnClose) btnClose.addEventListener('click', () => cerrarModal(overlay));
        if (overlay) overlay.addEventListener('click', e => { if (e.target === overlay) cerrarModal(overlay); });
        if (form) form.addEventListener('submit', enviarFormAgregar);

        initFormDropdown($('dropdown-ex-estatus'), $('ex-estatus'));
    }

    function resetFormAgregar() {
        const form = $('form-agregar-extraescolar');
        if (form) {
            form.querySelectorAll('input[type="text"],input[type="number"],input[type="date"],textarea').forEach(i => i.value = '');
            form.querySelectorAll('.error-msg-modal').forEach(e => e.textContent = '');
        }
        setDropdownFull($('dropdown-ex-estatus'), $('ex-estatus'), '1', 'Activo');
    }

    function enviarFormAgregar(e) {
        e.preventDefault();

        const data = {
            nombre: ($('ex-nombre')?.value || '').trim(),
            cupo_maximo: $('ex-cupo-maximo')?.value || null,
            duracion_semanas: ($('ex-semanas')?.value || '').trim(),
            costo_base: $('ex-costo')?.value || null,
            fecha_inicio: $('ex-fecha-inicio')?.value || '',
            fecha_fin: $('ex-fecha-fin')?.value || '',
            estatus: $('ex-estatus')?.value === '1',
            ubicacion: ($('ex-ubicacion')?.value || '').trim(),
            descripcion: $('ex-descripcion')?.value || '',
            requisitos: $('ex-requisitos')?.value || '',
        };

        let ok = true;
        if (!data.nombre) { showErr('err-ex-nombre', 'El nombre es requerido.'); ok = false; } else clearErr('err-ex-nombre');
        if (!data.duracion_semanas) { showErr('err-ex-semanas', 'La duración es requerida.'); ok = false; } else clearErr('err-ex-semanas');
        if (!data.fecha_inicio) { showErr('err-ex-fecha-inicio', 'La fecha de inicio es requerida.'); ok = false; } else clearErr('err-ex-fecha-inicio');
        if (!data.fecha_fin) { showErr('err-ex-fecha-fin', 'La fecha de fin es requerida.'); ok = false; } else clearErr('err-ex-fecha-fin');
        if (!data.ubicacion) { showErr('err-ex-ubicacion', 'La ubicación es requerida.'); ok = false; } else clearErr('err-ex-ubicacion');
        if (!ok) return;

        fetch('/admin/extraescolares', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(data),
        })
            .then(r => r.json())
            .then(res => {
                if (res.errors) { Object.entries(res.errors).forEach(([k, m]) => { const e = document.querySelector(`[name="${k}"] + .error-msg-modal`); if (e) e.textContent = m[0]; }); return; }
                if (res.error) { alert(res.error); return; }
                cerrarModal($('modal-agregar-extraescolar'));
                cargarActividades();
            })
            .catch(() => alert('Error al guardar la actividad.'));
    }

    // ── Modal VER ────────────────────────────────────────────
    function bindModalVer() {
        const btnClose = $('modal-close-ver-extraescolar');
        const btnCerrar = $('btn-cerrar-ver-extra');
        const overlay = $('modal-ver-extraescolar');
        if (btnClose) btnClose.addEventListener('click', () => cerrarModal(overlay));
        if (btnCerrar) btnCerrar.addEventListener('click', () => cerrarModal(overlay));
        if (overlay) overlay.addEventListener('click', e => { if (e.target === overlay) cerrarModal(overlay); });
    }

    function abrirVerExtra(id) {
        const overlay = $('modal-ver-extraescolar');
        const loader = $('ver-extra-loader');
        const content = $('ver-extra-content');
        if (!overlay) return;

        if (loader) loader.style.display = 'block';
        if (content) content.style.display = 'none';
        abrirModal(overlay);

        fetch(`/admin/extraescolares/${id}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(r => r.json())
            .then(({ actividad: a, inscritos }) => {
                setText('ve-nombre', a.nombre);
                setText('ve-ubicacion', a.ubicacion || '—');
                setText('ve-cupo', a.cupo_maximo || '—');
                setText('ve-inscritos', inscritos.length.toString());
                setText('ve-duracion', a.duracion_semanas);
                setText('ve-costo', a.costo_base != null ? `$${parseFloat(a.costo_base).toFixed(2)}` : '—');
                setText('ve-inicio', a.fecha_inicio ? new Date(a.fecha_inicio).toLocaleDateString('es-MX') : '—');
                setText('ve-fin', a.fecha_fin ? new Date(a.fecha_fin).toLocaleDateString('es-MX') : '—');
                setText('ve-estatus', (a.estatus == true || a.estatus == 1) ? 'Activo' : 'Inactivo');
                setText('ve-descripcion', a.descripcion || '—');
                setText('ve-requisitos', a.requisitos || '—');

                const tbody = $('ve-inscritos-tbody');
                if (tbody) {
                    tbody.innerHTML = inscritos.length === 0
                        ? '<tr><td colspan="5" style="text-align:center;color:var(--texto-suave);">Sin inscritos activos</td></tr>'
                        : inscritos.map(i => `
                            <tr>
                                <td>${esc(i.nombre)}</td>
                                <td>${esc(i.apellido_p)}</td>
                                <td>${esc(i.email || '—')}</td>
                                <td>${esc(i.telefono || '—')}</td>
                                <td>${i.fecha_asignacion ? new Date(i.fecha_asignacion).toLocaleDateString('es-MX') : '—'}</td>
                            </tr>`).join('');
                }

                if (loader) loader.style.display = 'none';
                if (content) content.style.display = 'block';

                const banner = $('editar-extra-lock-banner');
                if (banner) banner.style.display = 'flex';

            })
            .catch(() => { if (loader) loader.innerHTML = '<p style="color:#c0392b;">Error al cargar el detalle.</p>'; });
    }

    // ── Modal EDITAR ─────────────────────────────────────────
    function bindModalEditar() {
        const btnClose = $('modal-close-editar-extraescolar');
        const btnCancelar = $('btn-cancelar-editar-extra');
        const overlay = $('modal-editar-extraescolar');
        const form = $('form-editar-extraescolar');
        const btnHabilitar = $('btn-habilitar-edicion-extra');
        const btnSubmit = $('btn-submit-editar-extra');

        if (btnClose) btnClose.addEventListener('click', () => { cerrarModal(overlay); resetEditarEstado(); });
        if (btnCancelar) btnCancelar.addEventListener('click', () => { cerrarModal(overlay); resetEditarEstado(); });
        if (overlay) overlay.addEventListener('click', e => { if (e.target === overlay) { cerrarModal(overlay); resetEditarEstado(); } });

        if (btnHabilitar) {
            btnHabilitar.addEventListener('click', () => {
                habilitarCamposEditar(true);
                $('editar-extra-lock-banner').style.display = 'none';
                if (btnSubmit) btnSubmit.disabled = false;
            });
        }

        if (form) form.addEventListener('submit', enviarFormEditar);

        initFormDropdown($('dropdown-edit-ex-estatus'), $('edit-ex-estatus'));
    }

    function resetEditarEstado() {
        habilitarCamposEditar(false);
        const banner = $('editar-extra-lock-banner');
        if (banner) banner.style.display = 'flex';
        const btnSubmit = $('btn-submit-editar-extra');
        if (btnSubmit) btnSubmit.disabled = true;
    }

    function habilitarCamposEditar(habilitar) {
        const ids = ['edit-ex-nombre', 'edit-ex-cupo', 'edit-ex-semanas', 'edit-ex-costo', 'edit-ex-inicio', 'edit-ex-fin', 'edit-ex-ubicacion', 'edit-ex-descripcion', 'edit-ex-requisitos'];
        ids.forEach(id => { const el = $(id); if (el) el.disabled = !habilitar; });
    }

    function abrirEditarExtra(id) {
        const overlay = $('modal-editar-extraescolar');
        const loader = $('editar-extra-loader');
        const content = $('editar-extra-content');
        if (!overlay) return;

        resetEditarEstado();
        if (loader) loader.style.display = 'block';
        if (content) content.style.display = 'none';
        abrirModal(overlay);

        fetch(`/admin/extraescolares/${id}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(r => r.json())
            .then(({ actividad: a, inscritos }) => {
                $('edit-extra-id').value = a.id_extraescolar;
                setInput('edit-ex-nombre', a.nombre);
                setInput('edit-ex-cupo', a.cupo_maximo || '');
                setInput('edit-ex-semanas', a.duracion_semanas);
                setInput('edit-ex-costo', a.costo_base || '');
                setInput('edit-ex-inicio', a.fecha_inicio || '');
                setInput('edit-ex-fin', a.fecha_fin || '');
                setInput('edit-ex-ubicacion', a.ubicacion || '');
                setTextArea('edit-ex-descripcion', a.descripcion || '');
                setTextArea('edit-ex-requisitos', a.requisitos || '');

                const esActivo = a.estatus == true || a.estatus == 1;
                setDropdownFull($('dropdown-edit-ex-estatus'), $('edit-ex-estatus'), esActivo ? '1' : '0', esActivo ? 'Activo' : 'Inactivo');

                // Tabla inscritos con baja
                const tbody = $('edit-inscritos-tbody');
                if (tbody) {
                    tbody.innerHTML = inscritos.length === 0
                        ? '<tr><td colspan="5" style="text-align:center;color:var(--texto-suave);">Sin inscritos activos</td></tr>'
                        : inscritos.map(i => `
                            <tr>
                                <td>${esc(i.nombre)}</td>
                                <td>${esc(i.apellido_p)}</td>
                                <td>${esc(i.email || '—')}</td>
                                <td>${esc(i.telefono || '—')}</td>
                                <td>
                                    ${PERMISOS.edit ? `
                                    <button class="btn-baja-alumno" data-id="${i.id_inscripcionextra}" title="Dar de baja">
                                        <i class="ri-user-unfollow-line"></i> Baja
                                    </button>` : '—'}
                                </td>
                            </tr>`).join('');

                    tbody.querySelectorAll('.btn-baja-alumno').forEach(b => {
                        b.addEventListener('click', () => darDeBaja(+b.dataset.id, a.id_extraescolar));
                    });
                }

                if (loader) loader.style.display = 'none';
                if (content) content.style.display = 'block';
            })
            .catch(() => { if (loader) loader.innerHTML = '<p style="color:#c0392b;">Error al cargar la actividad.</p>'; });
    }

    function enviarFormEditar(e) {
        e.preventDefault();

        const id = $('edit-extra-id')?.value;
        if (!id) return;

        const data = {
            nombre: ($('edit-ex-nombre')?.value || '').trim(),
            cupo_maximo: $('edit-ex-cupo')?.value || null,
            duracion_semanas: ($('edit-ex-semanas')?.value || '').trim(),
            costo_base: $('edit-ex-costo')?.value || null,
            fecha_inicio: $('edit-ex-inicio')?.value || '',
            fecha_fin: $('edit-ex-fin')?.value || '',
            estatus: $('edit-ex-estatus')?.value === '1',
            ubicacion: ($('edit-ex-ubicacion')?.value || '').trim(),
            descripcion: $('edit-ex-descripcion')?.value || '',
            requisitos: $('edit-ex-requisitos')?.value || '',
        };

        let ok = true;
        if (!data.nombre) { showErr('err-edit-ex-nombre', 'El nombre es requerido.'); ok = false; } else clearErr('err-edit-ex-nombre');
        if (!data.duracion_semanas) { showErr('err-edit-ex-semanas', 'La duración es requerida.'); ok = false; } else clearErr('err-edit-ex-semanas');
        if (!data.fecha_inicio) { showErr('err-edit-ex-inicio', 'La fecha inicio es requerida.'); ok = false; } else clearErr('err-edit-ex-inicio');
        if (!data.fecha_fin) { showErr('err-edit-ex-fin', 'La fecha fin es requerida.'); ok = false; } else clearErr('err-edit-ex-fin');
        if (!data.ubicacion) { showErr('err-edit-ex-ubicacion', 'La ubicación es requerida.'); ok = false; } else clearErr('err-edit-ex-ubicacion');
        if (!ok) return;

        fetch(`/admin/extraescolares/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(data),
        })
            .then(r => r.json())
            .then(res => {
                if (res.error) { alert(res.error); return; }
                cerrarModal($('modal-editar-extraescolar'));
                resetEditarEstado();
                cargarActividades();
            })
            .catch(() => alert('Error al actualizar la actividad.'));
    }

    function darDeBaja(idInscripcion, idExtra) {
        if (!confirm('¿Dar de baja a este alumno de la actividad?')) return;

        fetch(`/admin/extraescolares/baja/${idInscripcion}`, {
            method: 'PUT',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
        })
            .then(r => r.json())
            .then(res => {
                if (res.error) { alert(res.error); return; }
                // Recargar el modal con datos actualizados
                abrirEditarExtra(idExtra);
                // Recargar grid
                cargarActividades();
            })
            .catch(() => alert('Error al dar de baja al alumno.'));
    }

    function eliminarExtra(id) {
        if (!confirm('¿Eliminar esta actividad extraescolar? Solo es posible si no tiene inscritos activos.')) return;

        fetch(`/admin/extraescolares/${id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
        })
            .then(r => r.json())
            .then(res => {
                if (res.error) { alert(res.error); return; }
                cargarActividades();
            })
            .catch(() => alert('Error al eliminar la actividad.'));
    }

    // ── Helpers dropdown ─────────────────────────────────────
    function initFormDropdown(dd, hidden) {
        if (!dd || !hidden) return;
        const trigger = el('.form-select-trigger', dd);
        const opts = el('.form-options-container', dd);
        if (!trigger || !opts) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            dd.classList.toggle('open');
        });

        opts.querySelectorAll('.form-option').forEach(opt => {
            opt.addEventListener('click', () => {
                el('.selected-text', trigger).textContent = opt.textContent.trim();
                el('.selected-text', trigger).dataset.value = opt.dataset.value;
                opts.querySelectorAll('.form-option').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                hidden.value = opt.dataset.value;
                dd.classList.remove('open');
            });
        });

        document.addEventListener('click', () => dd.classList.remove('open'));
    }

    function setDropdownFull(dd, hidden, val, label) {
        if (!dd) return;
        const span = el('.selected-text', dd);
        if (span) { span.textContent = label; span.dataset.value = val; }
        dd.querySelectorAll('.form-option').forEach(o => o.classList.toggle('selected', o.dataset.value === val));
        if (hidden) hidden.value = val;
    }

    // ── Helpers generales ────────────────────────────────────
    function abrirModal(overlay) { if (overlay) overlay.classList.add('open'); }
    function cerrarModal(overlay) { if (overlay) overlay.classList.remove('open'); }

    function setText(id, text) { const e = $(id); if (e) e.textContent = text; }
    function setInput(id, val) { const e = $(id); if (e) e.value = val; }
    function setTextArea(id, val) { const e = $(id); if (e) e.value = val; }
    function showErr(id, msg) { const e = $(id); if (e) e.textContent = msg; }
    function clearErr(id) { const e = $(id); if (e) e.textContent = ''; }
    function esc(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ── Boot ─────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const seccion = $('section-extraescolares');
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
