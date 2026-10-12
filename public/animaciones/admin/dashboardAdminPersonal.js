/* ==============================================
   dashboardAdminPersonal.js — Sección Personal
   Búsqueda, Filtros, Modal y Validación
   EGAU Chess | AMAAC
   ============================================== */

document.addEventListener('DOMContentLoaded', () => {

    // ---- Búsqueda y Filtros — Backend ----
    const buscador = document.getElementById('buscador-personal');
    const btnLimpiar = document.getElementById('btn-limpiar-personal');
    const tabla = document.getElementById('tabla-personal');
    const tbody = tabla ? tabla.querySelector('tbody') : null;
    const infoEl = document.querySelector('#section-personal .pagination-info');

    // Genera HTML de una fila de personal desde el JSON del backend
    const renderFila = (r) => {
        const rolTxt = r.nombre_rol || `Rol ${r.id_rol}`;
        const sedeTxt = r.info?.nombre_sede || (window.SEDES_MAP && window.SEDES_MAP[r.id_sede]) || 'Sin sede';
        const estatusBadge = r.estatus
            ? '<span class="badge badge-activo">Activo</span>'
            : '<span class="badge badge-inactivo">Inactivo</span>';
        const infoEsc = JSON.stringify(r.info).replace(/"/g, '&quot;');
        const permisos = window.PERMISOS_PERSONAL || { edit: true, admin: true };
        const btnEditar = permisos.edit
            ? `<button class="btn-icon btn-editar btn-editar-personal" title="Editar" data-id="${r.id}"><i class="ri-edit-line"></i></button>`
            : '';
        const btnEliminar = (permisos.edit && permisos.admin)
            ? `<button class="btn-icon btn-eliminar btn-eliminar-personal" title="Eliminar" data-id="${r.id}"><i class="ri-delete-bin-line"></i></button>`
            : '';

        return `<tr>
            <td>${r.id}</td>
            <td>${r.nombre}</td>
            <td>${rolTxt}</td>
            <td>${sedeTxt}</td>
            <td>${estatusBadge}</td>
            <td class="acciones">
                <button class="btn-icon btn-ver btn-ver-personal" title="Ver" data-id="${r.id}" data-info="${infoEsc}"><i class="ri-eye-line"></i></button>
                ${btnEditar}
                ${btnEliminar}
            </td>
        </tr>`;
    };


    let fetchActivoPe = null;
    const buscarBackend = window.buscarPersonalBackend = () => {
        if (fetchActivoPe) fetchActivoPe.abort();
        const controller = new AbortController();
        fetchActivoPe = controller;

        const q = buscador ? buscador.value.trim().toLowerCase() : '';
        const idRol = document.querySelector('#dropdown-nivel-personal .selected-text')?.getAttribute('data-value') || '';
        const idSede = document.querySelector('#dropdown-sede-personal .selected-text')?.getAttribute('data-value') || '';
        const status = document.querySelector('#dropdown-status-personal .selected-text')?.getAttribute('data-value') || '';

        const params = new URLSearchParams({ q, id_rol: idRol, id_sede: idSede, estatus: status });

        fetch(`/admin/buscar/personal?${params}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        })
            .then(r => r.json())
            .then(res => {
                fetchActivoPe = null;
                if (!tbody) return;
                if (res.error) { console.error(res.error); return; }
                tbody.innerHTML = res.data.map(renderFila).join('');
                if (infoEl) infoEl.textContent = `Mostrando ${res.total} resultado${res.total !== 1 ? 's' : ''}`;
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                console.error('Error al buscar personal:', err);
            });
    };

    if (tabla) {
        // Cargar al inicio
        buscarBackend();

        // Escuchar input en buscador con debounce
        if (buscador) {
            let debounceTimerPe = null;
            buscador.addEventListener('input', () => {
                clearTimeout(debounceTimerPe);
                debounceTimerPe = setTimeout(buscarBackend, 350);
            });
        }

        // Lógica de los Custom Dropdowns (Específicos de Personal)
        const customDropdowns = document.querySelectorAll('#section-personal .custom-dropdown');

        customDropdowns.forEach(dropdown => {
            const trigger = dropdown.querySelector('.custom-select-trigger');
            const options = dropdown.querySelectorAll('.custom-option');
            const selectedText = dropdown.querySelector('.selected-text');

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                customDropdowns.forEach(d => { if (d !== dropdown) d.classList.remove('open'); });
                dropdown.classList.toggle('open');
            });

            options.forEach(option => {
                option.addEventListener('click', (e) => {
                    e.stopPropagation();
                    options.forEach(opt => opt.classList.remove('selected'));
                    option.classList.add('selected');
                    const val = option.getAttribute('data-value');
                    selectedText.textContent = option.textContent;
                    selectedText.setAttribute('data-value', val);
                    dropdown.classList.remove('open');
                    buscarBackend();
                });
            });
        });

        // Cerrar dropdown si se hace clic fuera
        document.addEventListener('click', () => {
            customDropdowns.forEach(d => d.classList.remove('open'));
        });

        // Botón Limpiar
        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                if (buscador) buscador.value = '';
                document.querySelectorAll('#section-personal .custom-dropdown').forEach(dropdown => {
                    const opts = dropdown.querySelectorAll('.custom-option');
                    const sel = dropdown.querySelector('.selected-text');
                    opts.forEach(o => o.classList.remove('selected'));
                    if (opts.length > 0) {
                        opts[0].classList.add('selected');
                        sel.textContent = opts[0].textContent;
                        sel.setAttribute('data-value', opts[0].getAttribute('data-value'));
                    }
                });
                buscarBackend();
            });
        }

    }

    // ---- Lógica para Form Dropdowns (Modal de Personal) ----
    // IMPORTANTE: debe estar FUERA del bloque if(tabla) para funcionar siempre
    const formDropdownsPersonal = document.querySelectorAll('#modal-agregar-personal .form-dropdown');
    formDropdownsPersonal.forEach(dropdown => {
        const trigger = dropdown.querySelector('.form-select-trigger');
        const options = dropdown.querySelectorAll('.form-option');
        const selectedText = dropdown.querySelector('.selected-text');
        const hiddenInput = dropdown.querySelector('input[type="hidden"]');

        if (!trigger || !selectedText) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            formDropdownsPersonal.forEach(d => {
                if (d !== dropdown) d.classList.remove('open');
            });
            dropdown.classList.toggle('open');
        });

        options.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                options.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');

                const val = option.getAttribute('data-value');
                const text = option.textContent;

                selectedText.textContent = text;
                selectedText.setAttribute('data-value', val);
                if (hiddenInput) hiddenInput.value = val;

                dropdown.classList.remove('open');

                if (val !== "") {
                    dropdown.classList.remove('input-error');
                    if (hiddenInput) {
                        const errorMsg = document.getElementById(`err-${hiddenInput.id}`);
                        if (errorMsg) errorMsg.textContent = '';
                    }
                }
            });
        });
    });

    document.addEventListener('click', () => {
        formDropdownsPersonal.forEach(dropdown => dropdown.classList.remove('open'));
    });

    // ---- Modal: Agregar Personal ----
    const modalOverlay = document.getElementById('modal-agregar-personal');
    const btnAgregar = document.getElementById('btn-agregar-personal');
    const btnCerrarModal = document.getElementById('modal-close-personal');
    const btnCancelarModal = document.getElementById('btn-cancelar-modal-personal');
    const formAgregar = document.getElementById('form-agregar-personal');

    const abrirModal = () => {
        if (modalOverlay) modalOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    const cerrarModal = () => {
        if (modalOverlay) modalOverlay.classList.remove('open');
        document.body.style.overflow = '';
        if (formAgregar) formAgregar.reset();

        // Limpiar form-dropdowns
        document.querySelectorAll('#modal-agregar-personal .form-dropdown').forEach(dropdown => {
            const options = dropdown.querySelectorAll('.form-option');
            const selectedText = dropdown.querySelector('.selected-text');
            const hiddenInput = dropdown.querySelector('input[type="hidden"]');
            dropdown.classList.remove('input-error', 'input-ok');
            options.forEach(opt => opt.classList.remove('selected'));
            if (options.length > 0) {
                // No seleccionamos el primero por defecto si es "Selecciona..."
                if (selectedText) {
                    selectedText.textContent = "Selecciona una opción";
                    selectedText.setAttribute('data-value', "");
                }
                if (hiddenInput) hiddenInput.value = "";
            }
        });

        // Limpiar estados de validación
        document.querySelectorAll('#modal-agregar-personal .form-group-modal input').forEach(inp => {
            inp.classList.remove('input-error', 'input-ok');
        });
        document.querySelectorAll('#modal-agregar-personal .error-msg-modal').forEach(msg => {
            msg.textContent = '';
        });
    };

    const PERMISOS_PE = window.PERMISOS_PERSONAL || { edit: false };
    if (btnAgregar && PERMISOS_PE.edit) btnAgregar.addEventListener('click', abrirModal);
    if (btnCerrarModal) btnCerrarModal.addEventListener('click', cerrarModal);
    if (btnCancelarModal) btnCancelarModal.addEventListener('click', cerrarModal);

    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) cerrarModal();
        });
    }

    // ---- Validación del formulario Personal ----
    if (formAgregar) {
        const setError = (inputId, msgId, mensaje) => {
            const inp = document.getElementById(inputId);
            const msg = document.getElementById(msgId);
            const dropdown = inp ? inp.closest('.form-dropdown') : null;

            if (dropdown) {
                dropdown.classList.add('input-error');
                dropdown.classList.remove('input-ok');
            } else if (inp) {
                inp.classList.add('input-error');
                inp.classList.remove('input-ok');
            }
            if (msg) msg.textContent = mensaje;
        };

        const setOk = (inputId, msgId) => {
            const inp = document.getElementById(inputId);
            const msg = document.getElementById(msgId);
            const dropdown = inp ? inp.closest('.form-dropdown') : null;

            if (dropdown) {
                dropdown.classList.remove('input-error');
                dropdown.classList.add('input-ok');
            } else if (inp) {
                inp.classList.remove('input-error');
                inp.classList.add('input-ok');
            }
            if (msg) msg.textContent = '';
        };

        formAgregar.addEventListener('submit', (e) => {
            e.preventDefault();
            let valido = true;

            const fields = [
                { id: 'pe-nombre', err: 'err-pe-nombre', msg: 'El nombre es requerido.' },
                { id: 'pe-ap-paterno', err: 'err-pe-ap-paterno', msg: 'El apellido paterno es requerido.' },
                { id: 'pe-fecha-nacimiento', err: 'err-pe-fecha-nacimiento', msg: 'La fecha de nacimiento es requerida.' },
                { id: 'pe-rol', target: 'dropdown-pe-rol', err: 'err-pe-rol', msg: 'Selecciona un rol.' },
                { id: 'pe-sede', target: 'dropdown-pe-sede', err: 'err-pe-sede', msg: 'Selecciona una sede.' },
                { id: 'pe-genero', target: 'dropdown-pe-genero', err: 'err-pe-genero', msg: 'Selecciona un género.' },
                { id: 'pe-estado', target: 'dropdown-pe-estado', err: 'err-pe-estado', msg: 'Selecciona un estado.' },
                { id: 'pe-ciudad', err: 'err-pe-ciudad', msg: 'La ciudad es requerida.' },
                { id: 'pe-calle', err: 'err-pe-calle', msg: 'La calle es requerida.' },
                { id: 'pe-colonia', err: 'err-pe-colonia', msg: 'La colonia es requerida.' },
                { id: 'pe-numero', err: 'err-pe-numero', msg: 'El número es requerido.' },
                { id: 'pe-cp', err: 'err-pe-cp', msg: 'Ingresa un CP válido (5 dígitos).', type: 'cp' },
                { id: 'pe-correo', err: 'err-pe-correo', msg: 'Ingresa un correo válido.', type: 'email' },
                { id: 'pe-password', err: 'err-pe-password', msg: 'La contraseña debe tener al menos 6 caracteres.', min: 6 }
            ];

            fields.forEach(f => {
                const el = document.getElementById(f.id);
                const targetId = f.target || f.id;
                if (!el || el.value.trim() === '' || (f.min && el.value.length < f.min)) {
                    setError(targetId, f.err, f.msg);
                    valido = false;
                } else if (f.type === 'email') {
                    const reg = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!reg.test(el.value.trim())) {
                        setError(targetId, f.err, f.msg);
                        valido = false;
                    } else { setOk(targetId, f.err); }
                } else if (f.type === 'cp') {
                    const reg = /^\d{5}$/;
                    if (!reg.test(el.value.trim())) {
                        setError(targetId, f.err, f.msg);
                        valido = false;
                    } else { setOk(targetId, f.err); }
                } else { setOk(targetId, f.err); }
            });

            // Confirmar contraseña
            const pePass = document.getElementById('pe-password');
            const pePassConf = document.getElementById('pe-password-confirm');
            const esModoEdicion = !!formAgregar.getAttribute('data-edit-id');

            if (!esModoEdicion) {
                // Al crear: contraseña obligatoria (ya validada en el loop)
                if (pePassConf && pePassConf.value !== (pePass ? pePass.value : '')) {
                    setError('pe-password-confirm', 'err-pe-password-confirm', 'Las contraseñas no coinciden.');
                    valido = false;
                } else if (pePassConf) { setOk('pe-password-confirm', 'err-pe-password-confirm'); }
            } else {
                // Al editar: contraseña opcional, si la llenó debe coincidir
                setOk('pe-password', 'err-pe-password');
                if (pePass && pePass.value.length > 0) {
                    if (pePass.value.length < 6) {
                        setError('pe-password', 'err-pe-password', 'La contraseña debe tener al menos 6 caracteres.');
                        valido = false;
                    } else if (pePassConf && pePassConf.value !== pePass.value) {
                        setError('pe-password-confirm', 'err-pe-password-confirm', 'Las contraseñas no coinciden.');
                        valido = false;
                    } else if (pePassConf) { setOk('pe-password-confirm', 'err-pe-password-confirm'); }
                } else {
                    setOk('pe-password-confirm', 'err-pe-password-confirm');
                }
            }

            if (valido) {
                const btnSubmit = formAgregar.querySelector('.btn-modal-submit');
                const textoOriginal = btnSubmit.innerHTML;
                btnSubmit.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Guardando...';
                btnSubmit.disabled = true;

                const editId = formAgregar.getAttribute('data-edit-id');
                const isEditMode = !!editId;

                const peNombre = document.getElementById('pe-nombre');
                const peApP = document.getElementById('pe-ap-paterno');
                const peApM = document.getElementById('pe-ap-materno');
                const peFecha = document.getElementById('pe-fecha-nacimiento');
                const peGenero = document.getElementById('pe-genero');
                const peSede = document.getElementById('pe-sede');
                const peRol = document.getElementById('pe-rol');
                const peEstado = document.getElementById('pe-estado');
                const peCiudad = document.getElementById('pe-ciudad');
                const peCalle = document.getElementById('pe-calle');
                const peColonia = document.getElementById('pe-colonia');
                const peNumero = document.getElementById('pe-numero');
                const peCp = document.getElementById('pe-cp');
                const peCorreo = document.getElementById('pe-correo');
                const peTelefono = document.getElementById('pe-telefono');

                const calleCompleta = (peCalle ? peCalle.value.trim() : '') + ' | ' +
                    (peColonia ? peColonia.value.trim() : '') + ' | ' +
                    (peNumero ? peNumero.value.trim() : '');

                const payload = {
                    tipo_usuario: 'personal',
                    nombre: peNombre ? peNombre.value.trim() : '',
                    apellido_p: peApP ? peApP.value.trim() : '',
                    apellido_m: peApM ? peApM.value.trim() : '',
                    fecha_nacimiento: peFecha ? peFecha.value : '',
                    genero: peGenero ? peGenero.value : '',
                    id_rol: peRol ? parseInt(peRol.value) : null,
                    estado_residencia: peEstado ? peEstado.value : '',
                    ciudad: peCiudad ? peCiudad.value.trim() : '',
                    calle: calleCompleta,
                    codigo_postal: peCp ? peCp.value.trim() : '',
                    email: peCorreo ? peCorreo.value.trim() : '',
                    telefono: peTelefono ? peTelefono.value.trim() : '',
                    estatus: document.getElementById('pe-estatus') ? parseInt(document.getElementById('pe-estatus').value) : 1,
                };

                if (pePass && pePass.value) {
                    payload['contraseña'] = pePass.value;
                }

                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                const token = tokenMeta ? tokenMeta.getAttribute('content') : '';

                const url = isEditMode ? `/admin/editar/personal/${editId}` : '/admin/registrar';
                const method = isEditMode ? 'PUT' : 'POST';

                fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
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
                        alert(data.message || 'Personal guardado correctamente');
                        cerrarModal();
                        location.reload();
                    })
                    .catch(err => {
                        console.error('Error al guardar personal:', err);
                        alert('No se pudo guardar:\n' + (err.message || JSON.stringify(err)));
                        btnSubmit.innerHTML = textoOriginal;
                        btnSubmit.disabled = false;
                    });
            }
        });
    }

    // ---- Eventos Editar / Eliminar en tabla Personal ----
    document.addEventListener('click', (e) => {

        // --- VER ---
        const btnVer = e.target.closest('.btn-ver-personal');
        if (btnVer) {
            e.stopPropagation();
            const id = btnVer.getAttribute('data-id');

            const modalVerPe = document.getElementById('modal-ver-personal');
            const verLoader = document.getElementById('ver-personal-loader');
            const verContent = document.getElementById('ver-personal-content');

            if (verLoader) verLoader.style.display = 'block';
            if (verContent) verContent.style.display = 'none';
            if (modalVerPe) modalVerPe.classList.add('open');

            fetch(`/admin/obtener/personal/${id}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(info => {
                    if (info.error) { if (modalVerPe) modalVerPe.classList.remove('open'); alert('Error: ' + info.error); return; }

                    const setV = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '—'; };
                    const generoLabel = { f: 'Femenino', m: 'Masculino', o: 'Otro' };
                    const calcEdad = (f) => {
                        if (!f) return '—';
                        const nac = new Date(f), hoy = new Date();
                        let edad = hoy.getFullYear() - nac.getFullYear();
                        const m = hoy.getMonth() - nac.getMonth();
                        if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
                        return `${edad} años`;
                    };

                    setV('vpe-nombre', `${info.nombre || ''} ${info.apellido_p || ''} ${info.apellido_m || ''}`.trim());
                    setV('vpe-fecha-nac', info.fecha_nacimiento || '—');
                    setV('vpe-edad', calcEdad(info.fecha_nacimiento));
                    setV('vpe-genero', generoLabel[info.genero?.toLowerCase()] || info.genero || '—');
                    setV('vpe-telefono', info.telefono || '—');
                    setV('vpe-correo', info.email || '—');
                    setV('vpe-estatus', info.estatus ? 'Activo' : 'Inactivo');
                    setV('vpe-rol', info.nombre_rol || info.id_rol || '—');

                    const sedeNombre = (window.SEDES_MAP && window.SEDES_MAP[info.id_sede])
                        ? window.SEDES_MAP[info.id_sede]
                        : (info.nombre_sede || (info.id_sede ? `Sede ${info.id_sede}` : '—'));
                    setV('vpe-sede', sedeNombre);

                    setV('vpe-estado', info.estado_residencia || '—');
                    setV('vpe-ciudad', info.ciudad || '—');
                    setV('vpe-cp', info.codigo_postal || '—');

                    if (info.calle) {
                        const partes = info.calle.split(' | ');
                        setV('vpe-calle', partes[0] || info.calle);
                        setV('vpe-colonia', partes[1] || '—');
                        setV('vpe-numero', partes[2] || '—');
                    } else {
                        setV('vpe-calle', '—'); setV('vpe-colonia', '—'); setV('vpe-numero', '—');
                    }

                    if (verLoader) verLoader.style.display = 'none';
                    if (verContent) verContent.style.display = 'block';
                })
                .catch(err => {
                    if (modalVerPe) modalVerPe.classList.remove('open');
                    alert('Error al cargar datos: ' + err);
                });
        }

        // Editar — datos frescos del backend
        const btnEditar = e.target.closest('.btn-editar-personal');
        if (btnEditar) {
            const id = btnEditar.getAttribute('data-id');

            fetch(`/admin/obtener/personal/${id}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(info => {
                    if (info.error) { alert('Error: ' + info.error); return; }

                    if (formAgregar) {
                        formAgregar.setAttribute('data-edit-id', id);
                        const title = document.querySelector('#modal-agregar-personal .modal-title');
                        if (title) title.textContent = 'Editar Personal';

                        if (document.getElementById('pe-nombre')) document.getElementById('pe-nombre').value = info.nombre || '';
                        if (document.getElementById('pe-ap-paterno')) document.getElementById('pe-ap-paterno').value = info.apellido_p || '';
                        if (document.getElementById('pe-ap-materno')) document.getElementById('pe-ap-materno').value = info.apellido_m || '';
                        if (document.getElementById('pe-fecha-nacimiento')) document.getElementById('pe-fecha-nacimiento').value = info.fecha_nacimiento || '';
                        if (document.getElementById('pe-telefono')) document.getElementById('pe-telefono').value = info.telefono || '';
                        if (document.getElementById('pe-correo')) document.getElementById('pe-correo').value = info.email || '';
                        if (document.getElementById('pe-ciudad')) document.getElementById('pe-ciudad').value = info.ciudad || '';
                        if (document.getElementById('pe-cp')) document.getElementById('pe-cp').value = info.codigo_postal || '';

                        // Separar calle guardada
                        if (info.calle) {
                            const partes = info.calle.split(' | ');
                            if (document.getElementById('pe-calle')) document.getElementById('pe-calle').value = partes[0] || info.calle;
                            if (document.getElementById('pe-colonia')) document.getElementById('pe-colonia').value = partes[1] || '';
                            if (document.getElementById('pe-numero')) document.getElementById('pe-numero').value = partes[2] || '';
                        }

                        if (info.genero) {
                            const optG = document.querySelector(`#dropdown-pe-genero .form-option[data-value="${info.genero.toUpperCase()}"]`)
                                || document.querySelector(`#dropdown-pe-genero .form-option[data-value="${info.genero.toLowerCase()}"]`);
                            if (optG) optG.click();
                        }
                        if (info.estado_residencia) {
                            const optE = [...document.querySelectorAll('#dropdown-pe-estado .form-option')]
                                .find(o => o.getAttribute('data-value').toLowerCase() === info.estado_residencia.toLowerCase());
                            if (optE) optE.click();
                        }
                        if (info.id_rol) {
                            const optR = document.querySelector(`#dropdown-pe-rol .form-option[data-value="${info.id_rol}"]`);
                            if (optR) optR.click();
                        }

                        if (info.estatus !== undefined) {
                            const opt = document.querySelector(`#dropdown-pe-estatus .form-option[data-value="${info.estatus ? '1' : '0'}"]`);
                            if (opt) opt.click();
                        }
                        const passEl = document.getElementById('pe-password');
                        if (passEl) passEl.removeAttribute('required');

                        abrirModal();
                    }
                })
                .catch(err => alert('Error al cargar datos: ' + err));
        }

        // Eliminar
        const btnEliminar = e.target.closest('.btn-eliminar-personal');
        if (btnEliminar) {
            const id = btnEliminar.getAttribute('data-id');
            if (confirm('¿Seguro que deseas eliminar este registro de personal de forma permanente?')) {
                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                fetch(`/admin/eliminar/personal/${id}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : ''
                    }
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.error) alert(data.error);
                        else { alert(data.message); location.reload(); }
                    })
                    .catch(err => alert('Error: ' + err));
            }
        }
    });

    // Reset modal al cancelar
    if (btnCancelarModal) {
        btnCancelarModal.addEventListener('click', () => {
            if (formAgregar) formAgregar.removeAttribute('data-edit-id');
            const title = document.querySelector('#modal-agregar-personal .modal-title');
            if (title) title.textContent = 'Agregar Personal';
            const passEl = document.getElementById('pe-password');
            if (passEl) passEl.setAttribute('required', 'true');
        });
    }
    // ---- Cerrar modal Ver Personal ----
    const modalVerPe = document.getElementById('modal-ver-personal');
    const btnCerrarVerPe = document.getElementById('btn-cerrar-ver-personal');
    const btnCloseVerPe = document.getElementById('modal-close-ver-personal');

    if (btnCerrarVerPe) btnCerrarVerPe.addEventListener('click', () => modalVerPe.classList.remove('open'));
    if (btnCloseVerPe) btnCloseVerPe.addEventListener('click', () => modalVerPe.classList.remove('open'));
    if (modalVerPe) modalVerPe.addEventListener('click', (e) => { if (e.target === modalVerPe) modalVerPe.classList.remove('open'); });

});
