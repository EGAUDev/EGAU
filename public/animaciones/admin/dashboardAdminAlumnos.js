// ==============================================
//  dashboardAdminAlumnos.js — Sección Alumnos
//  Búsqueda, Filtros, Modal y Validación
//  EGAU Chess | AMAAC
// ==============================================

document.addEventListener('DOMContentLoaded', () => {

    // ---- Búsqueda y Filtros — Backend ----
    const buscador = document.getElementById('buscador');
    const btnLimpiar = document.getElementById('btn-limpiar');
    const tabla = document.getElementById('tabla-alumnos');
    const tbody = tabla ? tabla.querySelector('tbody') : null;
    const info = document.querySelector('.pagination-info');

    const calcNivel = (puntaje) => {
        if (puntaje >= 3000) return { nombre: 'Rey', emoji: '♔', css: 'badge-rey' };
        if (puntaje >= 1500) return { nombre: 'Reina', emoji: '♕', css: 'badge-reina' };
        if (puntaje >= 800) return { nombre: 'Torre', emoji: '♖', css: 'badge-torre' };
        if (puntaje >= 400) return { nombre: 'Alfil', emoji: '♗', css: 'badge-alfil' };
        if (puntaje >= 150) return { nombre: 'Caballo', emoji: '♘', css: 'badge-caballo' };
        return { nombre: 'Peón', emoji: '♙', css: 'badge-peon' };
    };

    // Función: genera el HTML de una fila de alumno desde el JSON del backend
    const renderFila = (r) => {
        const nv = calcNivel(r.info?.puntaje ?? 0);
        const nivelBadge = `<span class="badge ${nv.css}">${nv.emoji} ${nv.nombre}</span>`;
        const sedeTxt = (window.SEDES_MAP && window.SEDES_MAP[r.id_sede]) ? window.SEDES_MAP[r.id_sede] : `Sede ${r.id_sede}`;
        const estatusBadge = r.estatus
            ? '<span class="badge badge-activo">Activo</span>'
            : '<span class="badge badge-inactivo">Inactivo</span>';
        const infoEsc = JSON.stringify(r.info).replace(/"/g, '&quot;');
        const permisos = window.PERMISOS_ALUMNOS || { edit: true, admin: true };
        const btnEditar = permisos.edit
            ? `<button class="btn-icon btn-editar btn-editar-alumno" title="Editar" data-id="${r.id}"><i class="ri-edit-line"></i></button>`
            : '';
        const btnEliminar = (permisos.edit && permisos.admin)
            ? `<button class="btn-icon btn-eliminar btn-eliminar-alumno" title="Eliminar" data-id="${r.id}"><i class="ri-delete-bin-line"></i></button>`
            : '';

        return `<tr>
            <td>${r.id}</td>
            <td>${r.nombre}</td>
            <td>${r.edad}</td>
            <td>${nivelBadge}</td>
            <td>${sedeTxt}</td>
            <td>${estatusBadge}</td>
            <td class="acciones">
                <button class="btn-icon btn-ver btn-ver-alumno" title="Ver" data-id="${r.id}" data-info="${infoEsc}"><i class="ri-eye-line"></i></button>
                ${btnEditar}
                ${btnEliminar}
            </td>
        </tr>`;
    };

    // Función principal que hace fetch al backend
    let fetchActivo = null;
    const buscarBackend = () => {
        if (fetchActivo) fetchActivo.abort();
        const controller = new AbortController();
        fetchActivo = controller;

        const q = buscador ? buscador.value.trim().toLowerCase() : '';
        const nivel = document.querySelector('#dropdown-nivel .selected-text')?.getAttribute('data-value') || '';
        const idSede = document.querySelector('#dropdown-sede .selected-text')?.getAttribute('data-value') || '';
        const status = document.querySelector('#dropdown-status .selected-text')?.getAttribute('data-value') || '';

        const params = new URLSearchParams({ q, nivel, id_sede: idSede, estatus: status });

        fetch(`/admin/buscar/alumno?${params}`, {
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
                if (info) info.textContent = `Mostrando ${res.total} resultado${res.total !== 1 ? 's' : ''}`;
            })
            .catch(err => {
                if (err.name === 'AbortError') return;
                console.error('Error al buscar alumnos:', err);
            });
    };

    if (tabla) {
        // Cargar datos del backend al iniciar
        buscarBackend();

        // Escuchar input en buscar con debounce
        if (buscador) {
            let debounceTimer = null;
            buscador.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(buscarBackend, 350);
            });
        }

        // Lógica de los Custom Dropdowns (scoped al section-alumnos)
        const customDropdowns = document.querySelectorAll('#section-alumnos .custom-dropdown');

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

        document.addEventListener('click', () => {
            customDropdowns.forEach(d => d.classList.remove('open'));
        });

        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                if (buscador) buscador.value = '';
                const customDropdowns2 = document.querySelectorAll('#section-alumnos .custom-dropdown');
                customDropdowns2.forEach(dropdown => {
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

        const formDropdowns = document.querySelectorAll('#modal-agregar-alumno .form-dropdown');
        formDropdowns.forEach(dropdown => {
            const trigger = dropdown.querySelector('.form-select-trigger');
            const options = dropdown.querySelectorAll('.form-option');
            const selectedText = dropdown.querySelector('.selected-text');
            const hiddenInput = dropdown.querySelector('input[type="hidden"]');

            if (!trigger || !selectedText) return;

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                formDropdowns.forEach(d => {
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
                        const errorMsg = document.querySelector(`#err-${hiddenInput.id}`);
                        if (errorMsg) errorMsg.textContent = '';
                    }
                });
            });
        });

        document.addEventListener('click', () => {
            formDropdowns.forEach(dropdown => dropdown.classList.remove('open'));
        });
    }

    // ---- Modal: Agregar Alumno ----
    const modalOverlay = document.getElementById('modal-agregar-alumno');
    const btnAgregar = document.getElementById('btn-agregar');
    const btnCerrarModal = document.getElementById('modal-close-alumno');
    const btnCancelarModal = document.getElementById('btn-cancelar-modal');
    const formAgregar = document.getElementById('form-agregar-alumno');

    const abrirModal = () => {
        if (modalOverlay) modalOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    const cerrarModal = () => {
        if (modalOverlay) modalOverlay.classList.remove('open');
        document.body.style.overflow = '';
        if (formAgregar) formAgregar.reset();
        document.querySelectorAll('#modal-agregar-alumno .form-dropdown').forEach(dropdown => {
            const options = dropdown.querySelectorAll('.form-option');
            const selectedText = dropdown.querySelector('.selected-text');
            const hiddenInput = dropdown.querySelector('input[type="hidden"]');
            dropdown.classList.remove('input-error', 'input-ok');
            options.forEach(opt => opt.classList.remove('selected'));
            if (options.length > 0) {
                options[0].classList.add('selected');
                if (selectedText) {
                    selectedText.textContent = options[0].textContent;
                    selectedText.setAttribute('data-value', options[0].getAttribute('data-value'));
                }
                if (hiddenInput) hiddenInput.value = options[0].getAttribute('data-value');
            }
        });
        document.querySelectorAll('#modal-agregar-alumno .form-group-modal input').forEach(inp => {
            inp.classList.remove('input-error', 'input-ok');
        });
        document.querySelectorAll('#modal-agregar-alumno .error-msg-modal').forEach(msg => {
            msg.textContent = '';
        });
    };

    const PERMISOS_AL = window.PERMISOS_ALUMNOS || { edit: false };
    if (btnAgregar && PERMISOS_AL.edit) btnAgregar.addEventListener('click', abrirModal);
    if (btnCerrarModal) btnCerrarModal.addEventListener('click', cerrarModal);
    if (btnCancelarModal) btnCancelarModal.addEventListener('click', cerrarModal);

    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) cerrarModal();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('open')) {
            cerrarModal();
        }
    });

    const fechaNacInput = document.getElementById('al-fecha-nacimiento');
    if (fechaNacInput) {
        fechaNacInput.addEventListener('change', () => {
            const labelDivisor = document.getElementById('label-divisor-tutor');
            if (!labelDivisor || !fechaNacInput.value) return;
            const birthDate = new Date(fechaNacInput.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
            if (age < 18) {
                labelDivisor.textContent = 'Datos del Tutor (Requerido — el alumno es menor de edad)';
                labelDivisor.style.color = 'var(--naranja, #e74c3c)';
            } else {
                labelDivisor.textContent = 'Datos del Tutor (Opcional — el alumno es mayor de edad)';
                labelDivisor.style.color = 'var(--verde, #27ae60)';
            }
        });
    }

    document.querySelectorAll('#modal-agregar-alumno .toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = btn.querySelector('i');
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

            const alNombre = document.getElementById('al-nombre');
            if (!alNombre || alNombre.value.trim() === '') {
                setError('al-nombre', 'err-al-nombre', 'El nombre es requerido.');
                valido = false;
            } else { setOk('al-nombre', 'err-al-nombre'); }

            const alApP = document.getElementById('al-ap-paterno');
            if (!alApP || alApP.value.trim() === '') {
                setError('al-ap-paterno', 'err-al-ap-paterno', 'El apellido paterno es requerido.');
                valido = false;
            } else { setOk('al-ap-paterno', 'err-al-ap-paterno'); }

            const alFechaNacimiento = document.getElementById('al-fecha-nacimiento');
            if (!alFechaNacimiento || alFechaNacimiento.value.trim() === '') {
                setError('al-fecha-nacimiento', 'err-al-fecha-nacimiento', 'La fecha de nacimiento es requerida.');
                valido = false;
            } else { setOk('al-fecha-nacimiento', 'err-al-fecha-nacimiento'); }

            const alCorreo = document.getElementById('al-correo');
            const emailReg = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!alCorreo || !emailReg.test(alCorreo.value.trim())) {
                setError('al-correo', 'err-al-correo', 'Ingresa un correo válido.');
                valido = false;
            } else { setOk('al-correo', 'err-al-correo'); }

            const alGenero = document.getElementById('al-genero');
            if (!alGenero || alGenero.value === '') {
                setError('dropdown-al-genero', 'err-al-genero', 'Selecciona un género.');
                valido = false;
            } else { setOk('dropdown-al-genero', 'err-al-genero'); }

            const alSede = document.getElementById('al-sede');
            if (!alSede || alSede.value === '') {
                setError('dropdown-al-sede', 'err-al-sede', 'Selecciona una sede.');
                valido = false;
            } else { setOk('dropdown-al-sede', 'err-al-sede'); }

            const alPuntos = document.getElementById('al-puntos-inicial');
            if (!alPuntos || alPuntos.value.trim() === '' || isNaN(alPuntos.value)) {
                setError('al-puntos-inicial', 'err-al-puntos-inicial', 'Ingresa la puntuación inicial.');
                valido = false;
            } else if (parseInt(alPuntos.value) < 0 || parseInt(alPuntos.value) > 2000) {
                setError('al-puntos-inicial', 'err-al-puntos-inicial', 'El puntaje debe estar entre 0 y 2000.');
                valido = false;
            } else { setOk('al-puntos-inicial', 'err-al-puntos-inicial'); }

            const alEstado = document.getElementById('al-estado');
            if (!alEstado || alEstado.value === '') {
                setError('dropdown-al-estado', 'err-al-estado', 'Selecciona un estado.');
                valido = false;
            } else { setOk('dropdown-al-estado', 'err-al-estado'); }

            const alCiudad = document.getElementById('al-ciudad');
            if (!alCiudad || alCiudad.value.trim() === '') {
                setError('al-ciudad', 'err-al-ciudad', 'La ciudad es requerida.');
                valido = false;
            } else { setOk('al-ciudad', 'err-al-ciudad'); }

            const alCalle = document.getElementById('al-calle');
            if (!alCalle || alCalle.value.trim() === '') {
                setError('al-calle', 'err-al-calle', 'La calle es requerida.');
                valido = false;
            } else { setOk('al-calle', 'err-al-calle'); }

            const alColonia = document.getElementById('al-colonia');
            if (!alColonia || alColonia.value.trim() === '') {
                setError('al-colonia', 'err-al-colonia', 'La colonia es requerida.');
                valido = false;
            } else { setOk('al-colonia', 'err-al-colonia'); }

            const alNumero = document.getElementById('al-numero');
            if (!alNumero || alNumero.value.trim() === '') {
                setError('al-numero', 'err-al-numero', 'El número es requerido.');
                valido = false;
            } else { setOk('al-numero', 'err-al-numero'); }

            const alCP = document.getElementById('al-cp');
            const cpReg = /^\d{5}$/;
            if (!alCP || !cpReg.test(alCP.value.trim())) {
                setError('al-cp', 'err-al-cp', 'Ingresa un CP válido (5 dígitos).');
                valido = false;
            } else { setOk('al-cp', 'err-al-cp'); }

            const alPass = document.getElementById('al-password');
            const esModoEdicion = !!formAgregar.getAttribute('data-edit-id');
            if (!esModoEdicion) {
                if (!alPass || alPass.value.length < 6) {
                    setError('al-password', 'err-al-password', 'La contraseña debe tener al menos 6 caracteres.');
                    valido = false;
                } else { setOk('al-password', 'err-al-password'); }
            } else {
                if (alPass && alPass.value.length > 0 && alPass.value.length < 6) {
                    setError('al-password', 'err-al-password', 'La contraseña debe tener al menos 6 caracteres.');
                    valido = false;
                } else { setOk('al-password', 'err-al-password'); }
            }

            const alPassConf = document.getElementById('al-password-confirm');
            if (alPass && alPass.value.length > 0) {
                if (!alPassConf || alPassConf.value !== alPass.value) {
                    setError('al-password-confirm', 'err-al-password-confirm', 'Las contraseñas no coinciden.');
                    valido = false;
                } else { setOk('al-password-confirm', 'err-al-password-confirm'); }
            } else {
                setOk('al-password-confirm', 'err-al-password-confirm');
            }

            let edad = null;
            let isUnder18 = false;
            if (alFechaNacimiento && alFechaNacimiento.value.trim() !== '') {
                const birthDate = new Date(alFechaNacimiento.value);
                const today = new Date();
                edad = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) edad--;
                isUnder18 = edad < 18;
            }

            const labelDivisor = document.getElementById('label-divisor-tutor');
            if (labelDivisor) {
                if (isUnder18) {
                    labelDivisor.textContent = 'Datos del Tutor (Requerido — el alumno es menor de edad)';
                    labelDivisor.style.color = 'var(--naranja, #e74c3c)';
                } else {
                    labelDivisor.textContent = 'Datos del Tutor (Opcional)';
                    labelDivisor.style.color = '';
                }
            }

            const tuNombre = document.getElementById('tu-nombre');
            const tuApP = document.getElementById('tu-ap-paterno');
            const tuParentesco = document.getElementById('tu-parentesco');

            if (isUnder18) {
                if (!tuNombre || tuNombre.value.trim() === '') {
                    setError('tu-nombre', 'err-tu-nombre', 'Requerido para menores de edad.');
                    valido = false;
                } else { setOk('tu-nombre', 'err-tu-nombre'); }
                if (!tuApP || tuApP.value.trim() === '') {
                    setError('tu-ap-paterno', 'err-tu-ap-paterno', 'Requerido para menores de edad.');
                    valido = false;
                } else { setOk('tu-ap-paterno', 'err-tu-ap-paterno'); }
                if (!tuParentesco || tuParentesco.value === '') {
                    setError('dropdown-tu-parentesco', 'err-tu-parentesco', 'Requerido.');
                    valido = false;
                } else { setOk('dropdown-tu-parentesco', 'err-tu-parentesco'); }
            } else {
                setOk('tu-nombre', 'err-tu-nombre');
                setOk('tu-ap-paterno', 'err-tu-ap-paterno');
                setOk('dropdown-tu-parentesco', 'err-tu-parentesco');
                if ((tuNombre && tuNombre.value.trim() !== '') || (tuApP && tuApP.value.trim() !== '')) {
                    if (!tuParentesco || tuParentesco.value === '') {
                        setError('dropdown-tu-parentesco', 'err-tu-parentesco', 'Selecciona el parentesco del tutor.');
                        valido = false;
                    }
                }
            }

            const tuCorreo = document.getElementById('tu-correo');
            if (tuCorreo && tuCorreo.value.trim() !== '') {
                if (!emailReg.test(tuCorreo.value.trim())) {
                    setError('tu-correo', 'err-tu-correo', 'Ingresa un correo válido para el tutor.');
                    valido = false;
                } else { setOk('tu-correo', 'err-tu-correo'); }
            } else { setOk('tu-correo', 'err-tu-correo'); }

            if (valido) {
                const btnSubmit = formAgregar.querySelector('.btn-modal-submit');
                const textoOriginal = btnSubmit.innerHTML;
                btnSubmit.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Guardando...';
                btnSubmit.disabled = true;

                const editId = formAgregar.getAttribute('data-edit-id');
                const isEditMode = editId ? true : false;
                const idSede = alSede ? parseInt(alSede.value) || 1 : 1;
                const calleCompleta = alCalle.value.trim() + ' | ' + alColonia.value.trim() + ' | ' + alNumero.value.trim();

                const payload = {
                    tipo_usuario: 'alumno',
                    nombre: alNombre.value.trim(),
                    apellido_p: alApP.value.trim(),
                    apellido_m: document.getElementById('al-ap-materno') ? document.getElementById('al-ap-materno').value.trim() : '',
                    fecha_nacimiento: alFechaNacimiento.value,
                    genero: alGenero.value,
                    id_sede: idSede,
                    puntos: alPuntos ? alPuntos.value : 0,
                    estado_residencia: alEstado.value,
                    ciudad: alCiudad.value.trim(),
                    calle: calleCompleta,
                    codigo_postal: alCP.value.trim(),
                    email: alCorreo.value.trim(),
                    telefono: document.getElementById('al-telefono') ? document.getElementById('al-telefono').value.trim() : '',
                    estatus: document.getElementById('al-estatus') ? parseInt(document.getElementById('al-estatus').value) : 1,
                };

                const tuNombre = document.getElementById('tu-nombre');
                if (tuNombre && tuNombre.value.trim() !== '') {
                    payload.tu_nombre = tuNombre.value.trim();
                    payload.tu_ap_paterno = document.getElementById('tu-ap-paterno').value.trim();
                    payload.tu_ap_materno = document.getElementById('tu-ap-materno') ? document.getElementById('tu-ap-materno').value.trim() : '';
                    payload.tu_parentesco = document.getElementById('tu-parentesco').value;
                    payload.tu_telefono = document.getElementById('tu-telefono') ? document.getElementById('tu-telefono').value.trim() : '';
                    payload.tu_correo = document.getElementById('tu-correo') ? document.getElementById('tu-correo').value.trim() : '';
                }

                if (alPass && alPass.value) payload['contraseña'] = alPass.value;

                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                const token = tokenMeta ? tokenMeta.getAttribute('content') : '';
                const url = isEditMode ? `/admin/editar/alumno/${editId}` : '/admin/registrar';
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
                        alert(data.message || 'Alumno registrado correctamente');
                        cerrarModal();
                        location.reload();
                    })
                    .catch(err => {
                        console.error('Error al guardar alumno:', err);
                        alert('No se pudo guardar el alumno:\n' + (err.message || JSON.stringify(err)));
                        btnSubmit.innerHTML = textoOriginal;
                        btnSubmit.disabled = false;
                    });
            }
        });
    }

    // ---- Modal: Ver Detalle Alumno ----
    const modalVerAlumno = document.getElementById('modal-ver-alumno');
    const btnCerrarVer = document.getElementById('btn-cerrar-ver-alumno');
    const btnCloseVer = document.getElementById('modal-close-ver-alumno');
    const verLoader = document.getElementById('ver-alumno-loader');
    const verContent = document.getElementById('ver-alumno-content');

    const abrirModalVer = () => {
        if (modalVerAlumno) modalVerAlumno.classList.add('open');
    };

    const cerrarModalVer = () => {
        if (modalVerAlumno) modalVerAlumno.classList.remove('open');
    };

    if (btnCerrarVer) btnCerrarVer.addEventListener('click', cerrarModalVer);
    if (btnCloseVer) btnCloseVer.addEventListener('click', cerrarModalVer);
    if (modalVerAlumno) {
        modalVerAlumno.addEventListener('click', (e) => {
            if (e.target === modalVerAlumno) cerrarModalVer();
        });
    }

    const generoLabel = { f: 'Femenino', m: 'Masculino', o: 'Otro' };

    const calcEdad = (fechaStr) => {
        if (!fechaStr) return '—';
        const nac = new Date(fechaStr);
        const hoy = new Date();
        let edad = hoy.getFullYear() - nac.getFullYear();
        const m = hoy.getMonth() - nac.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
        return `${edad} años`;
    };

    const set = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = val || '—';
    };

    document.addEventListener('click', (e) => {

        const btnVer = e.target.closest('.btn-ver-alumno');
        if (btnVer) {
            e.stopPropagation();
            const id = btnVer.getAttribute('data-id');
            if (verLoader) verLoader.style.display = 'block';
            if (verContent) verContent.style.display = 'none';
            abrirModalVer();

            fetch(`/admin/obtener/alumno/${id}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(info => {
                    if (info.error) { cerrarModalVer(); alert('Error: ' + info.error); return; }
                    set('ver-nombre', `${info.nombre || ''} ${info.apellido_p || ''} ${info.apellido_m || ''}`.trim());
                    set('ver-fecha-nac', info.fecha_nacimiento || '—');
                    set('ver-edad', calcEdad(info.fecha_nacimiento));
                    set('ver-genero', generoLabel[info.genero?.toLowerCase()] || info.genero || '—');
                    set('ver-telefono', info.telefono || '—');
                    set('ver-correo', info.email || '—');
                    const puntaje = parseInt(info.puntaje ?? 0);
                    const nv = calcNivel(puntaje);
                    const nivel = `${nv.emoji} ${nv.nombre}`;
                    set('ver-estatus', info.estatus ? 'Activo' : 'Inactivo');
                    const sedeNombre = (window.SEDES_MAP && window.SEDES_MAP[info.id_sede])
                        ? window.SEDES_MAP[info.id_sede]
                        : (info.nombre_sede || `Sede ${info.id_sede}`);
                    set('ver-sede', sedeNombre);
                    set('ver-nivel', nivel);
                    set('ver-puntos', puntaje);
                    set('ver-estado', info.estado_residencia || '—');
                    set('ver-ciudad', info.ciudad || '—');
                    set('ver-cp', info.codigo_postal || '—');
                    if (info.calle) {
                        const partes = info.calle.split(' | ');
                        set('ver-calle', partes[0] || info.calle);
                        set('ver-colonia', partes[1] || '—');
                        set('ver-numero', partes[2] || '—');
                    } else {
                        set('ver-calle', '—'); set('ver-colonia', '—'); set('ver-numero', '—');
                    }
                    const tutorSection = document.getElementById('ver-tutor-section');
                    if (info.tu_nombre && info.tu_nombre.trim() !== '') {
                        set('ver-tu-nombre', `${info.tu_nombre || ''} ${info.tu_ap_paterno || ''} ${info.tu_ap_materno || ''}`.trim());
                        set('ver-tu-parentesco', info.tu_parentesco || '—');
                        set('ver-tu-telefono', info.tu_telefono || '—');
                        set('ver-tu-correo', info.tu_correo || '—');
                        if (tutorSection) tutorSection.style.display = 'block';
                    } else {
                        if (tutorSection) tutorSection.style.display = 'none';
                    }
                    if (verLoader) verLoader.style.display = 'none';
                    if (verContent) verContent.style.display = 'block';
                })
                .catch(err => { cerrarModalVer(); alert('Error al cargar datos: ' + err); });
        }

        const btnEditar = e.target.closest('.btn-editar-alumno');
        if (btnEditar) {
            const id = btnEditar.getAttribute('data-id');
            fetch(`/admin/obtener/alumno/${id}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(info => {
                    if (info.error) { alert('Error: ' + info.error); return; }
                    if (formAgregar) {
                        formAgregar.setAttribute('data-edit-id', id);
                        document.querySelector('#modal-agregar-alumno .modal-title').innerHTML = 'Editar Alumno';
                        document.getElementById('al-nombre').value = info.nombre || '';
                        document.getElementById('al-ap-paterno').value = info.apellido_p || '';
                        if (document.getElementById('al-ap-materno'))
                            document.getElementById('al-ap-materno').value = info.apellido_m || '';
                        document.getElementById('al-fecha-nacimiento').value = info.fecha_nacimiento || '';
                        if (document.getElementById('al-telefono'))
                            document.getElementById('al-telefono').value = info.telefono || '';
                        document.getElementById('al-ciudad').value = info.ciudad || '';
                        if (info.calle) {
                            const partes = info.calle.split(' | ');
                            document.getElementById('al-calle').value = partes[0] || info.calle;
                            if (document.getElementById('al-colonia')) document.getElementById('al-colonia').value = partes[1] || '';
                            if (document.getElementById('al-numero')) document.getElementById('al-numero').value = partes[2] || '';
                        }
                        document.getElementById('al-cp').value = info.codigo_postal || '';
                        document.getElementById('al-correo').value = info.email || '';
                        if (document.getElementById('al-puntos-inicial'))
                            document.getElementById('al-puntos-inicial').value = info.puntaje ?? 0;
                        document.getElementById('al-password').removeAttribute('required');
                        document.getElementById('al-password-confirm').removeAttribute('required');
                        if (info.genero) {
                            const opt = document.querySelector(`#dropdown-al-genero .form-option[data-value="${info.genero.toUpperCase()}"]`)
                                || document.querySelector(`#dropdown-al-genero .form-option[data-value="${info.genero.toLowerCase()}"]`);
                            if (opt) opt.click();
                        }
                        if (info.estado_residencia) {
                            const opt = [...document.querySelectorAll('#dropdown-al-estado .form-option')]
                                .find(o => o.getAttribute('data-value').toLowerCase() === info.estado_residencia.toLowerCase());
                            if (opt) opt.click();
                        }
                        if (info.id_sede) {
                            const opt = document.querySelector(`#dropdown-al-sede .form-option[data-value="${info.id_sede}"]`);
                            if (opt) opt.click();
                        }
                        if (info.estatus !== undefined) {
                            const opt = document.querySelector(`#dropdown-al-estatus .form-option[data-value="${info.estatus ? '1' : '0'}"]`);
                            if (opt) opt.click();
                        }
                        abrirModal();
                    }
                })
                .catch(err => alert('Error al cargar datos: ' + err));
        }

        const btnEliminar = e.target.closest('.btn-eliminar-alumno');
        if (btnEliminar) {
            const id = btnEliminar.getAttribute('data-id');
            if (confirm('¿Seguro que deseas eliminar este alumno de forma permanente?')) {
                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                fetch(`/admin/eliminar/alumno/${id}`, {
                    method: 'DELETE',
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

    const originalCerrarModal = window.cerrarModal;
    if (btnCancelarModal) {
        btnCancelarModal.addEventListener('click', () => {
            if (formAgregar) formAgregar.removeAttribute('data-edit-id');
            document.querySelector('#modal-agregar-alumno .modal-title').innerHTML = 'Agregar Alumno';
            document.getElementById('al-password').setAttribute('required', 'true');
        });
    }

    document.querySelectorAll('.btn-ver-alumno').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const id = btn.getAttribute('data-id');
            if (verLoader) verLoader.style.display = 'block';
            if (verContent) verContent.style.display = 'none';
            abrirModalVer();
            fetch(`/admin/obtener/alumno/${id}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(r => r.json())
                .then(info => {
                    if (info.error) { cerrarModalVer(); alert('Error: ' + info.error); return; }
                    set('ver-nombre', `${info.nombre || ''} ${info.apellido_p || ''} ${info.apellido_m || ''}`.trim());
                    set('ver-fecha-nac', info.fecha_nacimiento || '—');
                    set('ver-edad', calcEdad(info.fecha_nacimiento));
                    set('ver-genero', generoLabel[info.genero?.toLowerCase()] || info.genero || '—');
                    set('ver-telefono', info.telefono || '—');
                    set('ver-correo', info.email || '—');
                    const puntaje = parseInt(info.puntaje ?? 0);
                    const nivel = puntaje < 500 ? 'Principiante' : puntaje < 1000 ? 'Intermedio' : 'Avanzado';
                    set('ver-estatus', info.estatus ? 'Activo' : 'Inactivo');
                    const sedeNombre = (window.SEDES_MAP && window.SEDES_MAP[info.id_sede])
                        ? window.SEDES_MAP[info.id_sede]
                        : (info.nombre_sede || `Sede ${info.id_sede}`);
                    set('ver-sede', sedeNombre);
                    set('ver-nivel', nivel);
                    set('ver-puntos', puntaje);
                    set('ver-estado', info.estado_residencia || '—');
                    set('ver-ciudad', info.ciudad || '—');
                    set('ver-cp', info.codigo_postal || '—');
                    if (info.calle) {
                        const partes = info.calle.split(' | ');
                        set('ver-calle', partes[0] || info.calle);
                        set('ver-colonia', partes[1] || '—');
                        set('ver-numero', partes[2] || '—');
                    } else {
                        set('ver-calle', '—'); set('ver-colonia', '—'); set('ver-numero', '—');
                    }
                    const tutorSection = document.getElementById('ver-tutor-section');
                    if (info.tu_nombre && info.tu_nombre.trim() !== '') {
                        set('ver-tu-nombre', `${info.tu_nombre || ''} ${info.tu_ap_paterno || ''} ${info.tu_ap_materno || ''}`.trim());
                        set('ver-tu-parentesco', info.tu_parentesco || '—');
                        set('ver-tu-telefono', info.tu_telefono || '—');
                        set('ver-tu-correo', info.tu_correo || '—');
                        if (tutorSection) tutorSection.style.display = 'block';
                    } else {
                        if (tutorSection) tutorSection.style.display = 'none';
                    }
                    if (verLoader) verLoader.style.display = 'none';
                    if (verContent) verContent.style.display = 'block';
                })
                .catch(err => { cerrarModalVer(); alert('Error al cargar datos: ' + err); });
        });
    });

}); // ← este es el cierre del DOMContentLoaded