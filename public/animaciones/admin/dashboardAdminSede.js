/* ==============================================
   dashboardAdminSede.js — Sección Sedes
   Búsqueda y Modal
   EGAU Chess | AMAAC
   ============================================== */

document.addEventListener('DOMContentLoaded', () => {

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const tbody = document.querySelector('#tabla-sedes tbody');
    const infoEl = document.querySelector('#section-sede .pagination-info');

    // ── Render fila ──────────────────────────────────────────────────
    const renderFila = (s) => {
        const permisos = window.PERMISOS_SEDES || { ver: true, edit: true, admin: true };
        const btnEditar = permisos.edit
            ? `<button class="btn-icon btn-editar btn-editar-sede" title="Editar" data-id="${s.id}"><i class="ri-edit-line"></i></button>`
            : '';
        const btnEliminar = (permisos.edit && permisos.admin)
            ? `<button class="btn-icon btn-eliminar btn-eliminar-sede" title="Eliminar" data-id="${s.id}"><i class="ri-delete-bin-line"></i></button>`
            : '';

        return `<tr>
        <td>${s.id}</td>
        <td>${s.nombre}</td>
        <td>${s.calle || '—'}, ${s.ciudad || '—'}</td>
        <td>${s.telefono || '—'}</td>
        <td>${s.estado_residencia || '—'}</td>
        <td class="acciones">
            <button class="btn-icon btn-ver btn-ver-sede" title="Ver" data-id="${s.id}"><i class="ri-eye-line"></i></button>
            ${btnEditar}
            ${btnEliminar}
        </td>
    </tr>`;
    };

    // ── Buscar backend con AbortController ────────────────────────────
    let fetchActivo = null;
    const buscarSedes = () => {
        if (fetchActivo) fetchActivo.abort();
        const controller = new AbortController();
        fetchActivo = controller;

        const q = document.getElementById('buscador-sedes')?.value.trim() || '';
        const estado = document.querySelector('#dropdown-filtro-estado-sede .selected-text')
            ?.getAttribute('data-value') || '';

        const params = new URLSearchParams({ q, estado_residencia: estado });

        fetch(`/admin/buscar/sedes?${params}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        })
            .then(r => r.json())
            .then(res => {
                fetchActivo = null;
                if (!tbody) return;
                if (res.error) { console.error(res.error); return; }
                tbody.innerHTML = res.data.map(renderFila).join('');
                if (infoEl) infoEl.textContent = `Mostrando ${res.total} resultado${res.total !== 1 ? 's' : ''}`;
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                console.error('Error al buscar sedes:', err);
            });
    };

    buscarSedes();

    // ── Buscador input con debounce ───────────────────────────────────
    let debounceTimer = null;
    document.getElementById('buscador-sedes')
        ?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(buscarSedes, 350);
        });

    // ── Filtro estado ─────────────────────────────────────────────────
    const ddFiltroEstado = document.getElementById('dropdown-filtro-estado-sede');
    if (ddFiltroEstado) {
        const trigger = ddFiltroEstado.querySelector('.custom-select-trigger');
        const opts = ddFiltroEstado.querySelectorAll('.custom-option');
        const selText = ddFiltroEstado.querySelector('.selected-text');

        trigger?.addEventListener('click', (e) => {
            e.stopPropagation();
            ddFiltroEstado.classList.toggle('open');
        });

        opts.forEach(opt => {
            opt.addEventListener('click', (e) => {
                e.stopPropagation();
                opts.forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                selText.textContent = opt.textContent;
                selText.setAttribute('data-value', opt.getAttribute('data-value'));
                ddFiltroEstado.classList.remove('open');
                buscarSedes();
            });
        });

        document.addEventListener('click', () => ddFiltroEstado.classList.remove('open'));
    }

    // ── Limpiar filtros ───────────────────────────────────────────────
    document.getElementById('btn-limpiar-sedes')?.addEventListener('click', () => {
        const buscador = document.getElementById('buscador-sedes');
        if (buscador) buscador.value = '';

        const opts = ddFiltroEstado?.querySelectorAll('.custom-option');
        const selText = ddFiltroEstado?.querySelector('.selected-text');
        opts?.forEach(o => o.classList.remove('selected'));
        if (opts?.[0]) opts[0].classList.add('selected');
        if (selText) { selText.textContent = 'Todos los estados'; selText.setAttribute('data-value', ''); }

        buscarSedes();
    });

    // ── Modal Agregar/Editar ──────────────────────────────────────────
    const modalOverlay = document.getElementById('modal-agregar-sede');
    const btnAgregar = document.getElementById('btn-agregar-sede');
    const btnCerrarModal = document.getElementById('modal-close-sede');
    const btnCancelarModal = document.getElementById('btn-cancelar-modal-sede');
    const formAgregar = document.getElementById('form-agregar-sede');

    const abrirModal = () => {
        if (modalOverlay) modalOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    const cerrarModal = () => {
        if (modalOverlay) modalOverlay.classList.remove('open');
        document.body.style.overflow = '';
        if (formAgregar) formAgregar.reset();

        // Limpiar form-dropdowns
        document.querySelectorAll('#modal-agregar-sede .form-dropdown').forEach(dropdown => {
            const options = dropdown.querySelectorAll('.form-option');
            const selectedText = dropdown.querySelector('.selected-text');
            const hiddenInput = dropdown.querySelector('input[type="hidden"]');

            dropdown.classList.remove('input-error', 'input-ok');
            options.forEach(opt => opt.classList.remove('selected'));

            if (options.length > 0) {
                if (selectedText) {
                    selectedText.textContent = 'Selecciona un estado';
                    selectedText.setAttribute('data-value', '');
                }
                if (hiddenInput) hiddenInput.value = '';
            }
        });

        // Limpiar estados de validación
        document.querySelectorAll('#modal-agregar-sede .form-group-modal input').forEach(inp => {
            inp.classList.remove('input-error', 'input-ok');
        });
        document.querySelectorAll('#modal-agregar-sede .error-msg-modal').forEach(msg => {
            msg.textContent = '';
        });
    };

    const permisosSede = window.PERMISOS_SEDES || { edit: false };
    if (btnAgregar && permisosSede.edit) btnAgregar.addEventListener('click', abrirModal);
    if (btnCerrarModal) btnCerrarModal.addEventListener('click', cerrarModal);
    if (btnCancelarModal) btnCancelarModal.addEventListener('click', cerrarModal);

    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                cerrarModal();
            }
        });
    }

    // ---- Lógica para Form Dropdowns del modal Sede ----
    const formDropdownsSede = document.querySelectorAll('#modal-agregar-sede .form-dropdown');
    formDropdownsSede.forEach(dropdown => {
        const trigger = dropdown.querySelector('.form-select-trigger');
        const options = dropdown.querySelectorAll('.form-option');
        const selectedText = dropdown.querySelector('.selected-text');
        const hiddenInput = dropdown.querySelector('input[type="hidden"]');
        if (!trigger || !selectedText) return;
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            formDropdownsSede.forEach(d => { if (d !== dropdown) d.classList.remove('open'); });
            dropdown.classList.toggle('open');
        });
        options.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                options.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                selectedText.textContent = option.textContent;
                selectedText.setAttribute('data-value', option.getAttribute('data-value'));
                if (hiddenInput) hiddenInput.value = option.getAttribute('data-value');
                dropdown.classList.remove('open');
                dropdown.classList.remove('input-error');
            });
        });
    });
    document.addEventListener('click', () => {
        formDropdownsSede.forEach(d => d.classList.remove('open'));
    });

    // ---- Validación del formulario Sede ----
    if (formAgregar) {
        const setError = (inputId, msgId, mensaje) => {
            const inp = document.getElementById(inputId);
            const msg = document.getElementById(msgId);
            if (inp) { inp.classList.add('input-error'); inp.classList.remove('input-ok'); }
            if (msg) msg.textContent = mensaje;
        };

        const setOk = (inputId, msgId) => {
            const inp = document.getElementById(inputId);
            const msg = document.getElementById(msgId);
            if (inp) { inp.classList.remove('input-error'); inp.classList.add('input-ok'); }
            if (msg) msg.textContent = '';
        };

        formAgregar.addEventListener('submit', (e) => {
            e.preventDefault();
            let valido = true;

            const fields = [
                { id: 'se-nombre', err: 'err-se-nombre', msg: 'El nombre es obligatorio.' },
                { id: 'se-estado', target: 'dropdown-se-estado', err: 'err-se-estado', msg: 'Selecciona un estado.' },
                { id: 'se-ciudad', err: 'err-se-ciudad', msg: 'La ciudad es obligatoria.' },
                { id: 'se-cp', err: 'err-se-cp', msg: 'CP inválido (5 dígitos).', type: 'cp' },
                { id: 'se-calle', err: 'err-se-calle', msg: 'La dirección es obligatoria.' },
                { id: 'se-telefono', err: 'err-se-telefono', msg: 'Teléfono inválido (10 dígitos).', type: 'tel' },
                { id: 'se-correo', err: 'err-se-correo', msg: 'Email inválido.', type: 'email' }
            ];

            fields.forEach(f => {
                const el = document.getElementById(f.id);
                const targetId = f.target || f.id;

                if (!el || el.value.trim() === '') {
                    setError(targetId, f.err, f.msg);
                    valido = false;
                } else if (f.type === 'cp') {
                    if (!/^\d{5}$/.test(el.value.trim())) {
                        setError(targetId, f.err, f.msg);
                        valido = false;
                    } else { setOk(targetId, f.err); }
                } else if (f.type === 'tel') {
                    if (!/^\d{10}$/.test(el.value.trim())) {
                        setError(targetId, f.err, f.msg);
                        valido = false;
                    } else { setOk(targetId, f.err); }
                } else if (f.type === 'email') {
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value.trim())) {
                        setError(targetId, f.err, f.msg);
                        valido = false;
                    } else { setOk(targetId, f.err); }
                } else {
                    setOk(targetId, f.err);
                }
            });

            if (valido) {
                const btnSubmit = formAgregar.querySelector('.btn-modal-submit');
                const textoOriginal = btnSubmit.innerHTML;
                btnSubmit.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Guardando...';
                btnSubmit.disabled = true;

                const editId = formAgregar.getAttribute('data-edit-id');
                const isEditMode = !!editId;

                const payload = {
                    nombre: document.getElementById('se-nombre')?.value.trim() || '',
                    estado_residencia: document.getElementById('se-estado')?.value || '',
                    ciudad: document.getElementById('se-ciudad')?.value.trim() || '',
                    codigo_postal: document.getElementById('se-cp')?.value.trim() || '',
                    calle: document.getElementById('se-calle')?.value.trim() || '',
                    telefono: document.getElementById('se-telefono')?.value.trim() || '',
                    email: document.getElementById('se-correo')?.value.trim() || '',
                };

                const url = isEditMode ? `/admin/sedes/${editId}` : '/admin/sedes';
                const method = isEditMode ? 'PUT' : 'POST';

                fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF
                    },
                    body: JSON.stringify(payload)
                })
                    .then(async res => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            const errMsg = data.errors
                                ? Object.values(data.errors).flat().join('\n')
                                : (data.error || data.message || 'Error desconocido');
                            throw new Error(errMsg);
                        }
                        return data;
                    })
                    .then(data => {
                        alert(data.message || 'Sede guardada correctamente');
                        cerrarModal();
                        buscarSedes();
                    })
                    .catch(err => {
                        alert('No se pudo guardar la sede:\n' + (err.message || JSON.stringify(err)));
                        btnSubmit.innerHTML = textoOriginal;
                        btnSubmit.disabled = false;
                    });

            }

        });

    }
    // ── Ver / Editar / Eliminar ───────────────────────────────────────
    document.addEventListener('click', (e) => {

        // VER
        const btnVer = e.target.closest('.btn-ver-sede');
        if (btnVer) {
            const id = btnVer.getAttribute('data-id');
            const modalVer = document.getElementById('modal-ver-sede');
            if (modalVer) { modalVer.classList.add('open'); document.body.style.overflow = 'hidden'; }
            fetch(`/admin/sedes/${id}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(s => {
                    if (s.error) { alert(s.error); return; }
                    const setV = (elId, val) => { const el = document.getElementById(elId); if (el) el.textContent = val || '—'; };
                    setV('vse-nombre', s.nombre);
                    setV('vse-estado', s.estado_residencia);
                    setV('vse-ciudad', s.ciudad);
                    setV('vse-cp', s.codigo_postal);
                    setV('vse-calle', s.calle);
                    setV('vse-telefono', s.telefono);
                    setV('vse-email', s.email);
                    setV('vse-estatus', s.estatus ? 'Activo' : 'Inactivo');
                })
                .catch(err => alert('Error: ' + err));
        }

        // EDITAR
        const btnEditar = e.target.closest('.btn-editar-sede');
        if (btnEditar) {
            const id = btnEditar.getAttribute('data-id');
            fetch(`/admin/sedes/${id}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(s => {
                    if (s.error) { alert(s.error); return; }
                    if (formAgregar) {
                        formAgregar.setAttribute('data-edit-id', id);
                        const title = document.querySelector('#modal-agregar-sede .modal-title');
                        if (title) title.textContent = 'Editar Sede';
                        document.getElementById('se-nombre').value = s.nombre || '';
                        document.getElementById('se-ciudad').value = s.ciudad || '';
                        document.getElementById('se-cp').value = s.codigo_postal || '';
                        document.getElementById('se-calle').value = s.calle || '';
                        document.getElementById('se-telefono').value = s.telefono || '';
                        document.getElementById('se-correo').value = s.correo || '';
                        if (s.estado_residencia) {
                            const opt = [...document.querySelectorAll('#dropdown-se-estado .form-option')]
                                .find(o => o.getAttribute('data-value').toLowerCase() === s.estado_residencia.toLowerCase());
                            if (opt) opt.click();
                        }
                        abrirModal();
                    }
                })
                .catch(err => alert('Error: ' + err));
        }

        // ELIMINAR
        const btnEliminar = e.target.closest('.btn-eliminar-sede');
        if (btnEliminar) {
            const id = btnEliminar.getAttribute('data-id');
            if (confirm('¿Seguro que deseas eliminar esta sede de forma permanente?')) {
                fetch(`/admin/sedes/${id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF
                    }
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) alert(data.error);
                        else { alert(data.message); buscarSedes(); }
                    })
                    .catch(err => alert('Error: ' + err));
            }
        }
    });

    // Reset modal al cancelar
    if (btnCancelarModal) {
        btnCancelarModal.addEventListener('click', () => {
            if (formAgregar) formAgregar.removeAttribute('data-edit-id');
            const title = document.querySelector('#modal-agregar-sede .modal-title');
            if (title) title.textContent = 'Agregar Sede';
        });
    }
    const modalVerSede = document.getElementById('modal-ver-sede');
    const cerrarVerSede = () => { if (modalVerSede) { modalVerSede.classList.remove('open'); document.body.style.overflow = ''; } };
    document.getElementById('btn-cerrar-ver-sede')?.addEventListener('click', cerrarVerSede);
    document.getElementById('modal-close-ver-sede')?.addEventListener('click', cerrarVerSede);
    modalVerSede?.addEventListener('click', (e) => { if (e.target === modalVerSede) cerrarVerSede(); });
});
