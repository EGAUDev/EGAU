// ==============================================
//  dashboardAdminProfesores.js — Sección Profesores
//  Búsqueda, Modal y Validación
//  EGAU Chess | AMAAC
// ==============================================

document.addEventListener('DOMContentLoaded', () => {

    const sectionProfesores = document.getElementById('section-profesores');

    // Solo ejecutamos si hay al menos un elemento de profesores en pantalla
    if (!sectionProfesores) return;

    // ---- Búsqueda de Profesores — Backend ----
    const buscadorProf = document.getElementById('buscador-profesores');
    const btnLimpiarProf = document.getElementById('btn-limpiar-profesores');
    const gridProfesores = document.getElementById('grid-profesores');
    const msgEmpty = document.getElementById('profesores-empty');

    const renderTarjeta = (r) => {
        const sedeTxt = (window.SEDES_MAP && window.SEDES_MAP[r.id_sede])
            ? window.SEDES_MAP[r.id_sede]
            : (r.info?.nombre_sede || `Sede ${r.id_sede}`);
        const telefono = r.info?.telefono ?? 'N/A';
        const permisos = window.PERMISOS_PROFESORES || { edit: true, admin: true };
        const btnEditar = permisos.edit
            ? `<button class="btn-profesor-editar" data-id="${r.id}"><i class="ri-edit-line"></i> Editar</button>`
            : '';
        const btnEliminar = (permisos.edit && permisos.admin)
            ? `<button class="btn-profesor-eliminar" data-id="${r.id}"><i class="ri-delete-bin-line"></i></button>`
            : '';

        return `
<div class="profesor-card" data-nombre="${r.nombre}" data-email="${r.email}">
    <div class="profesor-card-body">
        <h3 class="profesor-nombre">${r.nombre}</h3>
        <div class="profesor-info">
            <span><i class="ri-mail-line"></i> ${r.email}</span>
            <span><i class="ri-phone-line"></i> ${telefono}</span>
        </div>
    </div>
    <div class="profesor-card-footer">
        <button class="btn-profesor-ver" title="Ver" data-id="${r.id}"><i class="ri-eye-line"></i> Ver</button>
        ${btnEditar}
        ${btnEliminar}
    </div>
</div>`;
    };

    let fetchActivoProf = null;
    const buscarProfesoresBackend = () => {
        if (fetchActivoProf) fetchActivoProf.abort();
        const controller = new AbortController();
        fetchActivoProf = controller;

        const q = buscadorProf ? buscadorProf.value.trim().toLowerCase() : '';
        const status = document.querySelector('#section-profesores .custom-dropdown .selected-text')?.getAttribute('data-value') || '';
        const params = new URLSearchParams({ q, estatus: status });

        fetch(`/admin/buscar/profesor?${params}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        })
            .then(r => r.json())
            .then(res => {
                fetchActivoProf = null;
                if (!gridProfesores) return;
                if (res.error) { console.error(res.error); return; }
                gridProfesores.innerHTML = res.data.map(renderTarjeta).join('');
                if (msgEmpty) msgEmpty.style.display = res.total === 0 ? 'block' : 'none';
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                console.error('Error al buscar profesores:', err);
            });
    };

    buscarProfesoresBackend();

    if (buscadorProf) {
        let debounceTimerProf = null;
        buscadorProf.addEventListener('input', () => {
            clearTimeout(debounceTimerProf);
            debounceTimerProf = setTimeout(buscarProfesoresBackend, 350);
        });
    }

    if (btnLimpiarProf) {
        btnLimpiarProf.addEventListener('click', () => {
            if (buscadorProf) buscadorProf.value = '';
            buscarProfesoresBackend();
        });
    }

    // ---- Modal: Agregar Profesor ----
    const modalProfesor = document.getElementById('modal-agregar-profesor');
    const btnAgregarProfesor = document.getElementById('btn-agregar-profesor');
    const btnCerrarModalProf = document.getElementById('modal-close-profesor');
    const btnCancelarModalProf = document.getElementById('btn-cancelar-modal-profesor');
    const formAgregarProf = document.getElementById('form-agregar-profesor');

    const abrirModalProfesor = () => {
        if (modalProfesor) modalProfesor.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    const cerrarModalProfesor = () => {
        if (modalProfesor) modalProfesor.classList.remove('open');
        document.body.style.overflow = '';
        if (formAgregarProf) formAgregarProf.reset();

        // Limpiar dropdowns dentro del modal de profesor (Ej: Género)
        if (modalProfesor) {
            modalProfesor.querySelectorAll('.form-dropdown').forEach(dropdown => {
                const options = dropdown.querySelectorAll('.form-option');
                const selectedText = dropdown.querySelector('.selected-text');
                const hiddenInput = dropdown.querySelector('input[type="hidden"]');

                dropdown.classList.remove('input-error', 'input-ok');
                options.forEach(opt => opt.classList.remove('selected'));

                if (options.length > 0) {
                    options[0].classList.add('selected'); // "Selecciona una opción"
                    if (selectedText) {
                        selectedText.textContent = options[0].textContent;
                        selectedText.setAttribute('data-value', options[0].getAttribute('data-value'));
                    }
                    if (hiddenInput) hiddenInput.value = options[0].getAttribute('data-value');
                }
            });

            // Limpiar estados de validación de inputs
            modalProfesor.querySelectorAll('.form-group-modal input').forEach(inp => {
                inp.classList.remove('input-error', 'input-ok');
            });
            modalProfesor.querySelectorAll('.error-msg-modal').forEach(msg => {
                msg.textContent = '';
            });

            // Revertir tipo de inputs de password
            modalProfesor.querySelectorAll('input[type="text"]').forEach(inp => {
                if (inp.id.includes('password')) {
                    inp.type = 'password';
                    const icon = inp.nextElementSibling.querySelector('i');
                    if (icon) icon.classList.replace('ri-eye-off-line', 'ri-eye-line');
                }
            });
        }
    };

    const PERMISOS_PR = window.PERMISOS_PROFESORES || { edit: false };
    if (btnAgregarProfesor && PERMISOS_PR.edit) btnAgregarProfesor.addEventListener('click', abrirModalProfesor);
    if (btnCerrarModalProf) btnCerrarModalProf.addEventListener('click', cerrarModalProfesor);
    if (btnCancelarModalProf) btnCancelarModalProf.addEventListener('click', cerrarModalProfesor);

    // Cerrar al hacer clic en el overlay (fuera del modal-box)
    if (modalProfesor) {
        modalProfesor.addEventListener('click', (e) => {
            if (e.target === modalProfesor) cerrarModalProfesor();
        });
    }

    // ---- Lógica para Form Dropdowns del modal Profesor ----
    const formDropdownsProf = document.querySelectorAll('#modal-agregar-profesor .form-dropdown');
    formDropdownsProf.forEach(dropdown => {
        const trigger = dropdown.querySelector('.form-select-trigger');
        const options = dropdown.querySelectorAll('.form-option');
        const selectedText = dropdown.querySelector('.selected-text');
        const hiddenInput = dropdown.querySelector('input[type="hidden"]');

        if (!trigger || !selectedText) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            formDropdownsProf.forEach(d => {
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
        formDropdownsProf.forEach(dropdown => dropdown.classList.remove('open'));
    });

    // Cerrar con Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalProfesor && modalProfesor.classList.contains('open')) {
            cerrarModalProfesor();
        }
    });

    // ---- Toggle mostrar/ocultar contraseña (específico para profesor) ----
    if (modalProfesor) {
        modalProfesor.querySelectorAll('.toggle-password').forEach(btn => {
            // Evitar duplicados si hay scripts globales
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);

            newBtn.addEventListener('click', () => {
                const targetId = newBtn.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = newBtn.querySelector('i');
                if (!input) return;

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('ri-eye-line', 'ri-eye-off-line');
                } else {
                    input.type = 'password';
                    icon.classList.replace('ri-eye-off-line', 'ri-eye-line');
                }
            });
        });
    }

    // ---- Validación del formulario de Profesor ----
    if (formAgregarProf) {
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

        formAgregarProf.addEventListener('submit', (e) => {
            e.preventDefault();
            let valido = true;

            // Nombre
            const prNombre = document.getElementById('pr-nombre');
            if (!prNombre || prNombre.value.trim() === '') {
                setError('pr-nombre', 'err-pr-nombre', 'El nombre es requerido.');
                valido = false;
            } else { setOk('pr-nombre', 'err-pr-nombre'); }

            // Apellido paterno
            const prApP = document.getElementById('pr-ap-paterno');
            if (!prApP || prApP.value.trim() === '') {
                setError('pr-ap-paterno', 'err-pr-ap-paterno', 'El apellido paterno es requerido.');
                valido = false;
            } else { setOk('pr-ap-paterno', 'err-pr-ap-paterno'); }

            // Correo
            const prCorreo = document.getElementById('pr-correo');
            const emailReg = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!prCorreo || !emailReg.test(prCorreo.value.trim())) {
                setError('pr-correo', 'err-pr-correo', 'Ingresa un correo válido.');
                valido = false;
            } else { setOk('pr-correo', 'err-pr-correo'); }

            // Fecha de nacimiento
            const prFechaNacimiento = document.getElementById('pr-fecha-nacimiento');
            if (!prFechaNacimiento || prFechaNacimiento.value.trim() === '') {
                setError('pr-fecha-nacimiento', 'err-pr-fecha-nacimiento', 'La fecha de nacimiento es requerida.');
                valido = false;
            } else { setOk('pr-fecha-nacimiento', 'err-pr-fecha-nacimiento'); }

            // Puntuación
            const prPuntos = document.getElementById('pr-puntos');
            if (!prPuntos || prPuntos.value.trim() === '' || prPuntos.value < 0) {
                setError('pr-puntos', 'err-pr-puntos', 'Debe ser 0 o mayor.');
                valido = false;
            } else { setOk('pr-puntos', 'err-pr-puntos'); }

            // Género
            const prGeneroDropdown = document.getElementById('dropdown-pr-genero');
            const prGeneroInput = document.getElementById('pr-genero');
            if (!prGeneroInput || prGeneroInput.value === '') {
                if (prGeneroDropdown) {
                    prGeneroDropdown.classList.add('input-error');
                    prGeneroDropdown.classList.remove('input-ok');
                }
                const msg = document.getElementById('err-pr-genero');
                if (msg) msg.textContent = 'Selecciona un género.';
                valido = false;
            } else {
                if (prGeneroDropdown) {
                    prGeneroDropdown.classList.remove('input-error');
                    prGeneroDropdown.classList.add('input-ok');
                }
                const msg = document.getElementById('err-pr-genero');
                if (msg) msg.textContent = '';
            }


            // Dirección: Estado
            const prEstado = document.getElementById('pr-estado');
            if (!prEstado || prEstado.value === '') {
                setError('dropdown-pr-estado', 'err-pr-estado', 'Selecciona un estado.');
                valido = false;
            } else { setOk('dropdown-pr-estado', 'err-pr-estado'); }

            // Dirección: Ciudad
            const prCiudad = document.getElementById('pr-ciudad');
            if (!prCiudad || prCiudad.value.trim() === '') {
                setError('pr-ciudad', 'err-pr-ciudad', 'La ciudad es requerida.');
                valido = false;
            } else { setOk('pr-ciudad', 'err-pr-ciudad'); }

            // Dirección: Calle
            const prCalle = document.getElementById('pr-calle');
            if (!prCalle || prCalle.value.trim() === '') {
                setError('pr-calle', 'err-pr-calle', 'La calle es requerida.');
                valido = false;
            } else { setOk('pr-calle', 'err-pr-calle'); }

            const prColonia = document.getElementById('pr-colonia');
            if (!prColonia || prColonia.value.trim() === '') {
                setError('pr-colonia', 'err-pr-colonia', 'La colonia es requerida.');
                valido = false;
            } else { setOk('pr-colonia', 'err-pr-colonia'); }

            const prNumero = document.getElementById('pr-numero');
            if (!prNumero || prNumero.value.trim() === '') {
                setError('pr-numero', 'err-pr-numero', 'El número es requerido.');
                valido = false;
            } else { setOk('pr-numero', 'err-pr-numero'); }

            // Dirección: CP
            const prCP = document.getElementById('pr-cp');
            const cpReg = /^\d{5}$/;
            if (!prCP || !cpReg.test(prCP.value.trim())) {
                setError('pr-cp', 'err-pr-cp', 'Ingresa un CP válido (5 dígitos).');
                valido = false;
            } else { setOk('pr-cp', 'err-pr-cp'); }

            // Sede
            const prSede = document.getElementById('pr-sede');
            if (!prSede || prSede.value === '') {
                setError('dropdown-pr-sede', 'err-pr-sede', 'Selecciona una sede.');
                valido = false;
            } else { setOk('dropdown-pr-sede', 'err-pr-sede'); }

            // Teléfono
            const prTelefono = document.getElementById('pr-telefono');
            if (prTelefono && prTelefono.value.trim() !== '') {
                if (prTelefono.value.length !== 10) {
                    setError('pr-telefono', 'err-pr-telefono', 'Ingrese un teléfono o celular de 10 dígitos.');
                    valido = false;
                } else { setOk('pr-telefono', 'err-pr-telefono'); }
            } else { setOk('pr-telefono', 'err-pr-telefono'); } // Limpiar error si está vacío


            // Contraseña (obligatoria al crear, opcional al editar)
            const esModoEdicion = !!formAgregarProf.getAttribute('data-edit-id');
            const prPass = document.getElementById('pr-password');
            const prPassConf = document.getElementById('pr-password-confirm');

            if (!esModoEdicion) {
                // Crear: contraseña requerida
                if (!prPass || prPass.value.length < 6) {
                    setError('pr-password', 'err-pr-password', 'La contraseña debe tener al menos 6 caracteres.');
                    valido = false;
                } else { setOk('pr-password', 'err-pr-password'); }

                if (!prPassConf || prPassConf.value !== (prPass ? prPass.value : '')) {
                    setError('pr-password-confirm', 'err-pr-password-confirm', 'Las contraseñas no coinciden.');
                    valido = false;
                } else { setOk('pr-password-confirm', 'err-pr-password-confirm'); }
            } else {
                // Editar: solo validar si el usuario escribió algo
                if (prPass && prPass.value.length > 0) {
                    if (prPass.value.length < 6) {
                        setError('pr-password', 'err-pr-password', 'La contraseña debe tener al menos 6 caracteres.');
                        valido = false;
                    } else if (!prPassConf || prPassConf.value !== prPass.value) {
                        setError('pr-password-confirm', 'err-pr-password-confirm', 'Las contraseñas no coinciden.');
                        valido = false;
                    } else {
                        setOk('pr-password', 'err-pr-password');
                        setOk('pr-password-confirm', 'err-pr-password-confirm');
                    }
                } else {
                    setOk('pr-password', 'err-pr-password');
                    setOk('pr-password-confirm', 'err-pr-password-confirm');
                }
            }

            if (valido) {
                const btnSubmit = formAgregarProf.querySelector('.btn-modal-submit');
                const textoOriginal = btnSubmit.innerHTML;
                btnSubmit.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Guardando...';
                btnSubmit.disabled = true;

                const editId = formAgregarProf.getAttribute('data-edit-id');
                const isEditMode = !!editId;

                // id_sede: usar el value del dropdown directamente (ya es el id_sede de la DB)
                const prSedeVal = document.getElementById('pr-sede');
                const idSede = prSedeVal ? parseInt(prSedeVal.value) || null : null;

                const prNombre = document.getElementById('pr-nombre');
                const prApP = document.getElementById('pr-ap-paterno');
                const prCorreo = document.getElementById('pr-correo');
                const prFechaNacimiento = document.getElementById('pr-fecha-nacimiento');
                const prGeneroInput = document.getElementById('pr-genero');
                const prEstado = document.getElementById('pr-estado');
                const prCiudad = document.getElementById('pr-ciudad');
                const prCalle = document.getElementById('pr-calle');
                const prColonia = document.getElementById('pr-colonia');
                const prNumero = document.getElementById('pr-numero');
                const prCP = document.getElementById('pr-cp');
                const prTelefono = document.getElementById('pr-telefono');
                // prPass y prPassConf ya están declaradas en el scope del submit

                const calleCompleta = (prCalle ? prCalle.value.trim() : '') + ' | ' +
                    (prColonia ? prColonia.value.trim() : '') + ' | ' +
                    (prNumero ? prNumero.value.trim() : '');

                const payload = {
                    tipo_usuario: 'profesor',
                    nombre: prNombre ? prNombre.value.trim() : '',
                    apellido_p: prApP ? prApP.value.trim() : '',
                    apellido_m: document.getElementById('pr-ap-materno') ? document.getElementById('pr-ap-materno').value.trim() : '',
                    email: prCorreo ? prCorreo.value.trim() : '',
                    fecha_nacimiento: prFechaNacimiento ? prFechaNacimiento.value : '',
                    genero: prGeneroInput ? prGeneroInput.value : '',
                    id_sede: idSede,
                    puntaje: prPuntos ? parseInt(prPuntos.value) || 0 : 0,
                    estado_residencia: prEstado ? prEstado.value : '',
                    ciudad: prCiudad ? prCiudad.value.trim() : '',
                    calle: calleCompleta,
                    codigo_postal: prCP ? prCP.value.trim() : '',
                    telefono: prTelefono ? prTelefono.value.trim() : '',
                    estatus: document.getElementById('pr-estatus') ? parseInt(document.getElementById('pr-estatus').value) : 1,
                };

                if (prPass && prPass.value) {
                    payload['contraseña'] = prPass.value;
                }

                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                const token = tokenMeta ? tokenMeta.getAttribute('content') : '';

                const url = isEditMode ? `/admin/editar/profesor/${editId}` : '/admin/registrar';
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
                        alert(data.message || 'Profesor guardado correctamente');
                        cerrarModalProfesor();
                        location.reload();
                    })
                    .catch(err => {
                        console.error('Error al guardar profesor:', err);
                        alert('No se pudo guardar el profesor:\n' + (err.message || JSON.stringify(err)));
                        btnSubmit.innerHTML = textoOriginal;
                        btnSubmit.disabled = false;
                    });
            }
        });
    }

    // ---- Eventos Editar / Eliminar en tarjetas de profsor ----
    document.addEventListener('click', (e) => {

        // --- VER ---
        const btnVer = e.target.closest('.btn-profesor-ver');
        if (btnVer) {
            e.stopPropagation();
            const id = btnVer.getAttribute('data-id');

            const modalVerProf = document.getElementById('modal-ver-profesor');
            const verLoader = document.getElementById('ver-profesor-loader');
            const verContent = document.getElementById('ver-profesor-content');

            if (verLoader) verLoader.style.display = 'block';
            if (verContent) verContent.style.display = 'none';
            if (modalVerProf) modalVerProf.classList.add('open');

            fetch(`/admin/obtener/profesor/${id}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(info => {
                    if (info.error) { modalVerProf.classList.remove('open'); alert('Error: ' + info.error); return; }

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

                    setV('vpr-nombre', `${info.nombre || ''} ${info.apellido_p || ''} ${info.apellido_m || ''}`.trim());
                    setV('vpr-fecha-nac', info.fecha_nacimiento || '—');
                    setV('vpr-edad', calcEdad(info.fecha_nacimiento));
                    setV('vpr-genero', generoLabel[info.genero?.toLowerCase()] || info.genero || '—');
                    setV('vpr-telefono', info.telefono || '—');
                    setV('vpr-correo', info.email || '—');
                    setV('vpr-estatus', info.estatus ? 'Activo' : 'Inactivo');

                    const sedeNombre = (window.SEDES_MAP && window.SEDES_MAP[info.id_sede])
                        ? window.SEDES_MAP[info.id_sede]
                        : (info.nombre_sede || `Sede ${info.id_sede}`);
                    setV('vpr-sede', sedeNombre);
                    setV('vpr-puntos', info.puntaje ?? 0);

                    setV('vpr-estado', info.estado_residencia || '—');
                    setV('vpr-ciudad', info.ciudad || '—');
                    setV('vpr-cp', info.codigo_postal || '—');

                    if (info.calle) {
                        const partes = info.calle.split(' | ');
                        setV('vpr-calle', partes[0] || info.calle);
                        setV('vpr-colonia', partes[1] || '—');
                        setV('vpr-numero', partes[2] || '—');
                    } else {
                        setV('vpr-calle', '—'); setV('vpr-colonia', '—'); setV('vpr-numero', '—');
                    }

                    if (verLoader) verLoader.style.display = 'none';
                    if (verContent) verContent.style.display = 'block';
                })
                .catch(err => {
                    if (modalVerProf) modalVerProf.classList.remove('open');
                    alert('Error al cargar datos: ' + err);
                });
        }

        // Editar — datos frescos del backend
        const btnEditar = e.target.closest('.btn-profesor-editar');
        if (btnEditar) {
            const id = btnEditar.getAttribute('data-id');

            fetch(`/admin/obtener/profesor/${id}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(info => {
                    if (info.error) { alert('Error: ' + info.error); return; }

                    if (formAgregarProf) {
                        formAgregarProf.setAttribute('data-edit-id', id);
                        const title = document.querySelector('#modal-agregar-profesor .modal-title');
                        if (title) title.textContent = 'Editar Profesor';

                        if (document.getElementById('pr-nombre')) document.getElementById('pr-nombre').value = info.nombre || '';
                        if (document.getElementById('pr-ap-paterno')) document.getElementById('pr-ap-paterno').value = info.apellido_p || '';
                        if (document.getElementById('pr-ap-materno')) document.getElementById('pr-ap-materno').value = info.apellido_m || '';
                        if (document.getElementById('pr-correo')) document.getElementById('pr-correo').value = info.email || '';
                        if (document.getElementById('pr-telefono')) document.getElementById('pr-telefono').value = info.telefono || '';
                        if (document.getElementById('pr-fecha-nacimiento')) document.getElementById('pr-fecha-nacimiento').value = info.fecha_nacimiento || '';
                        if (document.getElementById('pr-ciudad')) document.getElementById('pr-ciudad').value = info.ciudad || '';
                        if (document.getElementById('pr-cp')) document.getElementById('pr-cp').value = info.codigo_postal || '';
                        if (document.getElementById('pr-puntos')) document.getElementById('pr-puntos').value = info.puntaje ?? 0;

                        // Separar calle guardada
                        if (info.calle) {
                            const partes = info.calle.split(' | ');
                            if (document.getElementById('pr-calle')) document.getElementById('pr-calle').value = partes[0] || info.calle;
                            if (document.getElementById('pr-colonia')) document.getElementById('pr-colonia').value = partes[1] || '';
                            if (document.getElementById('pr-numero')) document.getElementById('pr-numero').value = partes[2] || '';
                        }

                        if (info.genero) {
                            const opt = document.querySelector(`#dropdown-pr-genero .form-option[data-value="${info.genero.toUpperCase()}"]`)
                                || document.querySelector(`#dropdown-pr-genero .form-option[data-value="${info.genero.toLowerCase()}"]`);
                            if (opt) opt.click();
                        }
                        if (info.estado_residencia) {
                            const opt = [...document.querySelectorAll('#dropdown-pr-estado .form-option')]
                                .find(o => o.getAttribute('data-value').toLowerCase() === info.estado_residencia.toLowerCase());
                            if (opt) opt.click();
                        }
                        if (info.id_sede) {
                            const opt = document.querySelector(`#dropdown-pr-sede .form-option[data-value="${info.id_sede}"]`);
                            if (opt) opt.click();
                        }

                        if (info.estatus !== undefined) {
                            const opt = document.querySelector(`#dropdown-pr-estatus .form-option[data-value="${info.estatus ? '1' : '0'}"]`);
                            if (opt) opt.click();
                        }

                        const passEl = document.getElementById('pr-password');
                        if (passEl) passEl.removeAttribute('required');

                        abrirModalProfesor();
                    }
                })
                .catch(err => alert('Error al cargar datos: ' + err));
        }

        // Eliminar
        const btnEliminar = e.target.closest('.btn-profesor-eliminar');
        if (btnEliminar) {
            const id = btnEliminar.getAttribute('data-id');
            if (confirm('¿Seguro que deseas eliminar este profesor de forma permanente?')) {
                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                fetch(`/admin/eliminar/profesor/${id}`, {
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

    // Reset al cerrar modal
    if (btnCancelarModalProf) {
        btnCancelarModalProf.addEventListener('click', () => {
            if (formAgregarProf) formAgregarProf.removeAttribute('data-edit-id');
            const title = document.querySelector('#modal-agregar-profesor .modal-title');
            if (title) title.textContent = 'Agregar Profesor';
            const passEl = document.getElementById('pr-password');
            if (passEl) passEl.setAttribute('required', 'true');
        });
    }

    // ---- Cerrar modal Ver Profesor ----
    const modalVerProf = document.getElementById('modal-ver-profesor');
    const btnCerrarVerProf = document.getElementById('btn-cerrar-ver-profesor');
    const btnCloseVerProf = document.getElementById('modal-close-ver-profesor');

    if (btnCerrarVerProf) btnCerrarVerProf.addEventListener('click', () => modalVerProf.classList.remove('open'));
    if (btnCloseVerProf) btnCloseVerProf.addEventListener('click', () => modalVerProf.classList.remove('open'));
    if (modalVerProf) modalVerProf.addEventListener('click', (e) => { if (e.target === modalVerProf) modalVerProf.classList.remove('open'); });


});
