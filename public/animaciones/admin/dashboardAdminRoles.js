/**
 * dashboardAdminRoles.js
 * EGAU Chess — Gestión de Roles y Permisos
 */
(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const PERMISOS = window.PERMISOS_ROLES || { ver: false, edit: false, admin: false };

    const $ = id => document.getElementById(id);
    const el = (sel, ctx = document) => ctx.querySelector(sel);

    // Estado
    const state = {
        roles: [],
        paginaActual: 1,
        porPagina: 10,
        filtros: { q: '', estatus: '' },
        modoEdicion: false,
        idEditando: null,
    };

    // ── Módulos de la matriz ─────────────────────────────────
    const MODULOS = [
        { id: 'alumno', nombre: 'Alumnos', hasEdit: true },
        { id: 'profesor', nombre: 'Profesores', hasEdit: true },
        { id: 'personal', nombre: 'Personal', hasEdit: true },
        { id: 'roles', nombre: 'Roles', hasEdit: true },
        { id: 'sedes', nombre: 'Sedes', hasEdit: true },
        { id: 'grupos', nombre: 'Grupos', hasEdit: true },
        { id: 'extracurriculares', nombre: 'Extraescolares', hasEdit: true },
        { id: 'estatus', nombre: 'Status', hasEdit: false },
        { id: 'pagos', nombre: 'Pagos', hasEdit: true },
        { id: 'niveles', nombre: 'Niveles', hasEdit: true },
    ];

    // ── Init ─────────────────────────────────────────────────
    function init() {
        initDropdownsFiltros();
        bindFiltros();
        cargarRoles();
        bindModalRol();
        bindModalVer();
    }

    // ── Dropdowns filtros ────────────────────────────────────
    function initDropdownsFiltros() {
        document.querySelectorAll('#section-roles .custom-dropdown').forEach(dd => {
            const trigger = el('.custom-select-trigger', dd);
            const opts = el('.custom-options-container', dd);
            if (!trigger || !opts) return;

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const open = dd.classList.contains('open');
                document.querySelectorAll('#section-roles .custom-dropdown.open').forEach(o => o.classList.remove('open'));
                if (!open) dd.classList.add('open');
            });

            opts.querySelectorAll('.custom-option').forEach(opt => {
                opt.addEventListener('click', () => {
                    el('.selected-text', trigger).textContent = opt.textContent.trim();
                    el('.selected-text', trigger).dataset.value = opt.dataset.value;
                    opts.querySelectorAll('.custom-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    dd.classList.remove('open');
                    state.filtros.estatus = opt.dataset.value;
                    state.paginaActual = 1;
                    renderTabla();
                    renderPaginacion();
                });
            });
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('#section-roles .custom-dropdown.open').forEach(o => o.classList.remove('open'));
        });
    }

    function bindFiltros() {
        const buscador = $('buscador-roles');
        if (buscador) {
            let t;
            buscador.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(() => {
                    state.filtros.q = buscador.value.trim().toLowerCase();
                    state.paginaActual = 1;
                    renderTabla();
                    renderPaginacion();
                }, 300);
            });
        }
        const btnLimpiar = $('btn-limpiar-roles');
        if (btnLimpiar) btnLimpiar.addEventListener('click', () => {
            if (buscador) buscador.value = '';
            state.filtros = { q: '', estatus: '' };
            state.paginaActual = 1;
            document.querySelectorAll('#section-roles .custom-dropdown').forEach(dd => {
                const first = el('.custom-option', el('.custom-options-container', dd));
                if (first) {
                    el('.selected-text', dd).textContent = first.textContent.trim();
                    el('.selected-text', dd).dataset.value = first.dataset.value;
                    el('.custom-options-container', dd).querySelectorAll('.custom-option').forEach(o => o.classList.remove('selected'));
                    first.classList.add('selected');
                }
            });
            renderTabla();
            renderPaginacion();
        });
    }

    // ── Carga ────────────────────────────────────────────────
    function cargarRoles() {
        const params = new URLSearchParams();
        if (state.filtros.q) params.set('q', state.filtros.q);
        if (state.filtros.estatus) params.set('estatus', state.filtros.estatus);

        fetch(`/admin/roles?${params}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(r => r.json())
            .then(json => {
                state.roles = json.data || [];
                renderTabla();
                renderPaginacion();
            })
            .catch(() => {
                const tbody = $('tbody-roles');
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#c0392b;padding:1.5rem;">Error al cargar roles.</td></tr>';
            });
    }

    // ── Renderizado tabla ────────────────────────────────────
    function rolesFiltrados() {
        return state.roles.filter(r => {
            if (state.filtros.q) {
                const q = state.filtros.q;
                if (!(`${r.id_rol} ${r.nombre} ${r.descripcion}`.toLowerCase().includes(q))) return false;
            }
            if (state.filtros.estatus) {
                const esActivo = r.estatus == true || r.estatus == 'true' || r.estatus == 1;
                if (state.filtros.estatus === 'activo' && !esActivo) return false;
                if (state.filtros.estatus === 'inactivo' && esActivo) return false;
            }
            return true;
        });
    }

    function renderTabla() {
        const tbody = $('tbody-roles');
        if (!tbody) return;

        const filtrados = rolesFiltrados();
        const inicio = (state.paginaActual - 1) * state.porPagina;
        const pagina = filtrados.slice(inicio, inicio + state.porPagina);

        if (pagina.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--texto-suave);">No se encontraron roles.</td></tr>';
            return;
        }

        tbody.innerHTML = pagina.map(r => {
            const esActivo = r.estatus == true || r.estatus == 'true' || r.estatus == 1;
            const esAdmin = r.administrativo == true || r.administrativo == 'true' || r.administrativo == 1;
            const tipoBadge = esAdmin
                ? '<span class="badge badge-rol-admin">Administrador</span>'
                : '<span class="badge badge-rol-inactivo" style="background:#f0f0f0;color:#555;">Estándar</span>';
            const statBadge = esActivo
                ? '<span class="badge badge-rol-activo">Activo</span>'
                : '<span class="badge badge-rol-inactivo">Inactivo</span>';

            return `
                <tr>
                    <td>${r.id_rol}</td>
                    <td><strong>${esc(r.nombre)}</strong></td>
                    <td><div class="role-description">${esc(r.descripcion)}</div></td>
                    <td>${tipoBadge}</td>
                    <td>${statBadge}</td>
                    <td class="acciones">
                        <button class="btn-icon btn-ver btn-ver-rol" data-id="${r.id_rol}" title="Ver">
                            <i class="ri-eye-line"></i>
                        </button>
                        ${PERMISOS.edit ? `
                        <button class="btn-icon btn-editar btn-editar-rol" data-id="${r.id_rol}" title="Editar">
                            <i class="ri-edit-line"></i>
                        </button>` : ''}
                        ${(PERMISOS.edit && PERMISOS.admin) ? `
                        <button class="btn-icon btn-eliminar btn-eliminar-rol" data-id="${r.id_rol}" title="Eliminar">
                            <i class="ri-delete-bin-line"></i>
                        </button>` : ''}
                    </td>
                </tr>`;
        }).join('');

        tbody.querySelectorAll('.btn-ver-rol').forEach(b => b.addEventListener('click', () => abrirVerRol(+b.dataset.id)));
        tbody.querySelectorAll('.btn-editar-rol').forEach(b => b.addEventListener('click', () => abrirEditarRol(+b.dataset.id)));
        tbody.querySelectorAll('.btn-eliminar-rol').forEach(b => b.addEventListener('click', () => eliminarRol(+b.dataset.id)));
    }

    function renderPaginacion() {
        const info = $('roles-pag-info');
        const btns = $('roles-pag-btns');
        if (!btns) return;

        const filtrados = rolesFiltrados();
        const total = filtrados.length;
        const paginas = Math.ceil(total / state.porPagina);

        if (info) info.textContent = `${total} rol${total !== 1 ? 'es' : ''}`;

        btns.innerHTML = '';
        const prev = document.createElement('button');
        prev.className = 'pag-btn'; prev.innerHTML = '<i class="ri-arrow-left-s-line"></i>';
        prev.disabled = state.paginaActual === 1;
        prev.addEventListener('click', () => { if (state.paginaActual > 1) { state.paginaActual--; renderTabla(); renderPaginacion(); } });
        btns.appendChild(prev);

        for (let i = 1; i <= paginas; i++) {
            const b = document.createElement('button');
            b.className = `pag-btn${i === state.paginaActual ? ' active' : ''}`;
            b.textContent = i;
            b.addEventListener('click', () => { state.paginaActual = i; renderTabla(); renderPaginacion(); });
            btns.appendChild(b);
        }

        const next = document.createElement('button');
        next.className = 'pag-btn'; next.innerHTML = '<i class="ri-arrow-right-s-line"></i>';
        next.disabled = state.paginaActual >= paginas || paginas === 0;
        next.addEventListener('click', () => { if (state.paginaActual < paginas) { state.paginaActual++; renderTabla(); renderPaginacion(); } });
        btns.appendChild(next);
    }

    // ── Modal VER ────────────────────────────────────────────
    function bindModalVer() {
        const btnCerrar = $('btn-cerrar-ver-rol');
        const btnClose = $('modal-close-ver-rol');
        const overlay = $('modal-ver-rol');
        if (btnCerrar) btnCerrar.addEventListener('click', () => cerrarModal(overlay));
        if (btnClose) btnClose.addEventListener('click', () => cerrarModal(overlay));
        if (overlay) overlay.addEventListener('click', e => { if (e.target === overlay) cerrarModal(overlay); });
    }

    function abrirVerRol(id) {
        const overlay = $('modal-ver-rol');
        const loader = $('ver-rol-loader');
        const content = $('ver-rol-content');
        if (!overlay) return;

        if (loader) loader.style.display = 'block';
        if (content) content.style.display = 'none';
        abrirModal(overlay);

        fetch(`/admin/roles/${id}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(r => r.json())
            .then(rol => {
                setText('vr-nombre', rol.nombre);
                setText('vr-tipo', (rol.administrativo == true || rol.administrativo == 1) ? 'Administrador' : 'Estándar');
                setText('vr-estatus', (rol.estatus == true || rol.estatus == 1) ? 'Activo' : 'Inactivo');
                setText('vr-descripcion', rol.descripcion);

                const tbody = $('vr-permisos-tbody');
                if (tbody) {
                    tbody.innerHTML = MODULOS.map(m => {
                        const verKey = m.id === 'estatus' ? 'estatus_ver' : `${m.id}_ver`;
                        const editKey = `${m.id}_edit`;
                        const ver = (rol[verKey] == true || rol[verKey] == 1) ? '✓' : '—';
                        const edit = m.hasEdit ? ((rol[editKey] == true || rol[editKey] == 1) ? '✓' : '—') : 'N/A';
                        const verStyle = ver === '✓' ? 'color:var(--verde,#1e8e3e);font-weight:700;' : 'color:var(--texto-suave);';
                        const editStyle = edit === '✓' ? 'color:var(--verde,#1e8e3e);font-weight:700;' : 'color:var(--texto-suave);';
                        return `<tr>
                            <td><strong>${m.nombre}</strong></td>
                            <td class="text-center" style="${verStyle}">${ver}</td>
                            <td class="text-center" style="${editStyle}">${edit}</td>
                        </tr>`;
                    }).join('');
                }

                if (loader) loader.style.display = 'none';
                if (content) content.style.display = 'block';
            })
            .catch(() => { if (loader) loader.innerHTML = '<p style="color:#c0392b;">Error al cargar el rol.</p>'; });
    }

    // ── Modal CREAR/EDITAR ───────────────────────────────────
    function bindModalRol() {
        const btnAgregar = $('btn-agregar-rol');
        const btnCancelar = $('btn-cancelar-modal-rol');
        const btnClose = $('modal-close-rol');
        const overlay = $('modal-agregar-rol');
        const form = $('form-agregar-rol');

        if (btnAgregar && PERMISOS.edit) btnAgregar.addEventListener('click', () => abrirCrearRol());
        if (btnCancelar) btnCancelar.addEventListener('click', () => cerrarModal(overlay));
        if (btnClose) btnClose.addEventListener('click', () => cerrarModal(overlay));
        if (overlay) overlay.addEventListener('click', e => { if (e.target === overlay) cerrarModal(overlay); });
        if (form) form.addEventListener('submit', enviarFormRol);

        // Dropdowns del modal
        initFormDropdowns(overlay);
    }

    function initFormDropdowns(ctx) {
        if (!ctx) return;
        ctx.querySelectorAll('.form-dropdown').forEach(dd => {
            const trigger = el('.form-select-trigger', dd);
            const opts = el('.form-options-container', dd);
            const hidden = el('input[type="hidden"]', dd);
            if (!trigger || !opts) return;

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const open = dd.classList.contains('open');
                ctx.querySelectorAll('.form-dropdown.open').forEach(o => o.classList.remove('open'));
                if (!open) dd.classList.add('open');
            });

            opts.querySelectorAll('.form-option').forEach(opt => {
                opt.addEventListener('click', () => {
                    el('.selected-text', trigger).textContent = opt.textContent.trim();
                    el('.selected-text', trigger).dataset.value = opt.dataset.value;
                    opts.querySelectorAll('.form-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    if (hidden) hidden.value = opt.dataset.value;
                    dd.classList.remove('open');
                });
            });
        });

        document.addEventListener('click', () => {
            if (ctx) ctx.querySelectorAll('.form-dropdown.open').forEach(o => o.classList.remove('open'));
        });
    }

    function abrirCrearRol() {
        state.modoEdicion = false;
        state.idEditando = null;

        const titulo = $('modal-rol-titulo');
        if (titulo) titulo.textContent = 'Agregar Nuevo Rol';

        const label = $('btn-rol-label');
        if (label) label.textContent = 'Guardar Rol';

        resetFormRol();
        abrirModal($('modal-agregar-rol'));
    }

    function abrirEditarRol(id) {
        state.modoEdicion = true;
        state.idEditando = id;

        const titulo = $('modal-rol-titulo');
        if (titulo) titulo.textContent = 'Editar Rol';

        const label = $('btn-rol-label');
        if (label) label.textContent = 'Actualizar Rol';

        const overlay = $('modal-agregar-rol');

        fetch(`/admin/roles/${id}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF } })
            .then(r => r.json())
            .then(rol => {
                setInput('ro-nombre', rol.nombre || '');
                setTextarea('ro-descripcion', rol.descripcion || '');

                // Tipo dropdown
                const esAdmin = rol.administrativo == true || rol.administrativo == 1;
                setDropdownValue('dropdown-ro-tipo', 'ro-tipo', esAdmin ? 'true' : 'false', esAdmin ? 'Administrador' : 'Estándar');

                // Estatus dropdown
                const esActivo = rol.estatus == true || rol.estatus == 1;
                setDropdownValue('dropdown-ro-estatus', 'ro-estatus', esActivo ? 'true' : 'false', esActivo ? 'Activo' : 'Inactivo');

                // Permisos
                MODULOS.forEach(m => {
                    const verKey = m.id === 'estatus' ? 'estatus_ver' : `${m.id}_ver`;
                    const editKey = `${m.id}_edit`;
                    const chkVer = $(`perm-${m.id}-ver`);
                    const chkEdit = $(`perm-${m.id}-edit`);
                    if (chkVer) chkVer.checked = (rol[verKey] == true || rol[verKey] == 1);
                    if (chkEdit) chkEdit.checked = (rol[editKey] == true || rol[editKey] == 1);
                });

                abrirModal(overlay);
            })
            .catch(() => alert('Error al cargar el rol.'));
    }

    function resetFormRol() {
        const form = $('form-agregar-rol');
        if (!form) return;
        form.querySelectorAll('input[type="text"], textarea').forEach(i => i.value = '');
        form.querySelectorAll('input[type="checkbox"]').forEach(c => c.checked = false);
        setDropdownValue('dropdown-ro-tipo', 'ro-tipo', 'false', 'Estándar');
        setDropdownValue('dropdown-ro-estatus', 'ro-estatus', 'true', 'Activo');
        form.querySelectorAll('.error-msg-modal').forEach(e => e.textContent = '');
    }

    function enviarFormRol(e) {
        e.preventDefault();

        const nombre = ($('ro-nombre')?.value || '').trim();
        const descripcion = ($('ro-descripcion')?.value || '').trim();
        const estatusVal = $('ro-estatus')?.value === 'true';
        const adminVal = $('ro-tipo')?.value === 'true';

        // Validación básica
        let ok = true;
        if (!nombre) { showErr('err-ro-nombre', 'El nombre es requerido.'); ok = false; } else clearErr('err-ro-nombre');
        if (!descripcion) { showErr('err-ro-descripcion', 'La descripción es requerida.'); ok = false; } else clearErr('err-ro-descripcion');
        if (!ok) return;

        const body = { nombre, descripcion, estatus: estatusVal, administrativo: adminVal };
        MODULOS.forEach(m => {
            const verKey = m.id === 'estatus' ? 'estatus_ver' : `${m.id}_ver`;
            const editKey = `${m.id}_edit`;
            body[verKey] = !!($(`perm-${m.id}-ver`)?.checked);
            if (m.hasEdit) body[editKey] = !!($(`perm-${m.id}-edit`)?.checked);
        });

        const url = state.modoEdicion ? `/admin/roles/${state.idEditando}` : '/admin/roles';
        const method = state.modoEdicion ? 'PUT' : 'POST';

        fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(body),
        })
            .then(r => r.json())
            .then(res => {
                if (res.errors) {
                    Object.entries(res.errors).forEach(([k, msgs]) => {
                        const el = $(`err-ro-${k}`);
                        if (el) el.textContent = msgs[0];
                    });
                    return;
                }
                if (res.error) { alert(res.error); return; }
                cerrarModal($('modal-agregar-rol'));
                cargarRoles();
            })
            .catch(() => alert('Error al guardar el rol.'));
    }

    function eliminarRol(id) {
        if (!confirm('¿Eliminar este rol? Esta acción no se puede deshacer.')) return;

        fetch(`/admin/roles/${id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
        })
            .then(r => r.json())
            .then(res => {
                if (res.error) { alert(res.error); return; }
                cargarRoles();
            })
            .catch(() => alert('Error al eliminar el rol.'));
    }

    // ── Helpers ──────────────────────────────────────────────
    function abrirModal(overlay) { if (overlay) overlay.classList.add('open'); }
    function cerrarModal(overlay) { if (overlay) overlay.classList.remove('open'); }

    function setText(id, text) { const e = $(id); if (e) e.textContent = text || '—'; }
    function setInput(id, val) { const e = $(id); if (e) e.value = val; }
    function setTextarea(id, val) { const e = $(id); if (e) e.value = val; }

    function setDropdownValue(ddId, hiddenId, val, label) {
        const dd = $(ddId); const hidden = $(hiddenId);
        if (dd) {
            const span = el('.selected-text', dd);
            if (span) { span.textContent = label; span.dataset.value = val; }
            dd.querySelectorAll('.form-option').forEach(o => {
                o.classList.toggle('selected', o.dataset.value === val);
            });
        }
        if (hidden) hidden.value = val;
    }

    function showErr(id, msg) { const e = $(id); if (e) e.textContent = msg; }
    function clearErr(id) { const e = $(id); if (e) e.textContent = ''; }
    function esc(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ── Boot ─────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        const seccion = $('section-roles');
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
