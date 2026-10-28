// ==============================================
//  dashboardAdminGrupos.js — Sección Grupos
//  Búsqueda, Modal y Lógica de Horarios Dinámicos
//  EGAU Chess | AMAAC
// ==============================================

document.addEventListener('DOMContentLoaded', () => {

    const sectionGrupos = document.getElementById('section-grupos');

    // Solo se ejecuta si estamos en una vista que contiene la sección de grupos
    if (!sectionGrupos) return;

    // Token
    const token = document.querySelector('meta[name="user-token"]')?.getAttribute('content') || '';

    // Referencias a contenedores
    const gridGrupos = document.getElementById('grid-grupos');
    const tbodyCursos = document.getElementById('tbody-cursos');
    const msgEmptyGrupos = document.getElementById('grupos-empty');

    // ==========================================
    // CARGA INICIAL Y BÚSQUEDA
    // ==========================================

    const cargarGrupos = async (q = '') => {
        try {
            const idProfesor = window.ID_PROFESOR;
            let url = '/admin/grupos/buscar';
            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (idProfesor) params.set('id_profesor', idProfesor);
            if (params.toString()) url += '?' + params.toString();

            const resp = await fetch(url, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const result = await resp.json();

            if (gridGrupos) gridGrupos.innerHTML = '';

            if (result.data && result.data.length > 0) {
                if (msgEmptyGrupos) msgEmptyGrupos.style.display = 'none';
                result.data.forEach(grupo => {
                    renderGrupoCard(grupo);
                });
            } else {
                if (msgEmptyGrupos) msgEmptyGrupos.style.display = 'block';
            }
        } catch (error) {
            console.error("Error al cargar grupos:", error);
        }
    };

    const cargarCursos = async (q = '') => {
        try {
            const url = q ? `/admin/cursos/buscar?q=${encodeURIComponent(q)}` : '/admin/cursos/buscar';
            const resp = await fetch(url, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const result = await resp.json();

            if (tbodyCursos) tbodyCursos.innerHTML = '';

            if (result.data && result.data.length > 0) {
                result.data.forEach(curso => {
                    renderCursoRow(curso);
                });
            } else {
                if (tbodyCursos) tbodyCursos.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:16px;">No se encontraron cursos.</td></tr>';
            }
        } catch (error) {
            console.error("Error al cargar cursos:", error);
        }
    };

    // ==========================================
    // FUNCIONES RENDER
    // ==========================================

    const diasMap = {
        0: 'Domingo', 1: 'Lunes', 2: 'Martes', 3: 'Miércoles',
        4: 'Jueves', 5: 'Viernes', 6: 'Sábado', 7: 'Domingo'
    };

    // Marca la opción con ese valor en un dropdown (o lo deja vacío si no existe)
    function elegirOpcion(idDropdown, valor, textoVacio) {
        const dropdown = document.getElementById(idDropdown);
        if (!dropdown) return;
        const opciones = dropdown.querySelectorAll('.form-option');
        const opcion = [...opciones].find(o => valor !== null && valor !== undefined && valor !== '' && o.getAttribute('data-value') === String(valor));
        opciones.forEach(o => o.classList.toggle('selected', o === opcion));
        dropdown.querySelector('input[type="hidden"]').value = opcion ? opcion.getAttribute('data-value') : '';
        dropdown.querySelector('.selected-text').textContent = opcion ? opcion.textContent : textoVacio;
    }

    // Al poner la fecha de inicio se elige solo el periodo que la contiene
    const inputInicioGrupo = document.getElementById('gr-fecha-inicio');
    if (inputInicioGrupo) {
        inputInicioGrupo.addEventListener('change', () => {
            const fecha = inputInicioGrupo.value;
            const opcion = [...document.querySelectorAll('#dropdown-gr-periodo .form-option')]
                .find(o => fecha >= o.getAttribute('data-desde') && fecha < o.getAttribute('data-hasta'));
            if (opcion) elegirOpcion('dropdown-gr-periodo', opcion.getAttribute('data-value'), 'Selecciona un periodo...');
        });
    }

    function getNivelLabel(nivel) {
        const n = parseInt(nivel) || 0;
        if (n === 0) return { label: 'Básico', color: 'verde' };
        if (n < 1000) return { label: 'Intermedio', color: 'azul' };
        return { label: 'Avanzado', color: 'morado' };
    }

    function renderGrupoCard(grupo) {
        if (!gridGrupos) return;

        // Determinar clase de badge de nivel
        const nivelInfo = getNivelLabel(grupo.nivel);
        let badgeClass = nivelInfo.color;

        // Progreso de inscritos
        const pct = grupo.cupo_maximo > 0 ? Math.min((grupo.inscritos / grupo.cupo_maximo) * 100, 100) : 0;

        // Horarios formatea
        let horariosHtml = '';
        if (grupo.horarios && grupo.horarios.length > 0) {
            horariosHtml = grupo.horarios.map(h => {
                const diaNombre = diasMap[h.dia_semana] || 'Día';
                return `<i class="ri-time-line"></i> ${diaNombre} ${h.hora_inicio} - ${h.hora_fin}`;
            }).join('</div><div class="grupo-info-line">');
        } else {
            horariosHtml = `<i class="ri-time-line"></i> Sin horario asignado`;
        }

        // Permisos
        const p = window.PERMISOS_GRUPOS || { ver: false, edit: false, admin: false };

        const card = document.createElement('div');
        card.className = 'grupo-card';
        card.setAttribute('data-nombre', grupo.codigo_grupo);
        card.setAttribute('data-nivel', grupo.nivel || '');
        card.setAttribute('data-profesor', grupo.nombre_profesor);

        card.innerHTML = `
            <div class="grupo-card-header">
                <h3 class="grupo-nombre" title="${grupo.nombre_curso}">${grupo.codigo_grupo}</h3>
                <span class="badge ${badgeClass}">${nivelInfo.label}</span>
            </div>
            <div class="grupo-card-body">
                <div class="grupo-info-line">
                    <i class="ri-user-star-line"></i> Prof. ${grupo.nombre_profesor}
                </div>
                <div class="grupo-inscritos-wrapper">
                    <div class="grupo-info-line">
                        <i class="ri-group-line"></i> ${grupo.inscritos} / ${grupo.cupo_maximo} inscritos
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: ${pct}%;"></div>
                    </div>
                </div>
                <div class="grupo-info-line">
                    ${horariosHtml}
                </div>
            </div>
            <div class="grupo-card-footer">
                <button class="btn-grupo-ver" title="Ver" data-id="${grupo.id_grupo}"><i class="ri-eye-line"></i> Ver</button>
                ${p.edit ? `<button class="btn-grupo-editar" data-id="${grupo.id_grupo}"><i class="ri-edit-line"></i> Editar</button>` : ''}
                ${(p.edit && p.admin) ? `<button class="btn-grupo-eliminar" data-id="${grupo.id_grupo}"><i class="ri-delete-bin-line"></i></button>` : ''}
            </div>
        `;

        gridGrupos.appendChild(card);
    }

    function renderCursoRow(curso) {
        if (!tbodyCursos) return;

        // Formato moneda
        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
        const costo = formatter.format(curso.costo_base || 0);

        // Nivel Badge
        const nivelInfo = getNivelLabel(curso.nivel);
        let badgeClass = nivelInfo.color;

        const estatusHtml = curso.estatus
            ? `<span style="color: #1e8e3e; font-size: 12px; font-weight: 600;"><i class="ri-checkbox-circle-fill"></i> Activo</span>`
            : `<span style="color: var(--texto-suave); font-size: 12px; font-weight: 600;"><i class="ri-close-circle-fill"></i> Inactivo</span>`;

        const p = window.PERMISOS_GRUPOS || { ver: false, edit: false, admin: false };

        let accionesHtml = `<div style="display: flex; gap: 8px; justify-content: center;">`;
        accionesHtml += `<button title="Ver" class="btn-ver-curso" data-id="${curso.id_curso}" style="background:none; border:none; color:var(--texto-suave); font-size:18px; cursor:pointer;" onmouseover="this.style.color='var(--naranja)'" onmouseout="this.style.color='var(--texto-suave)'"><i class="ri-eye-line"></i></button>`;
        if (p.edit) {
            accionesHtml += `<button title="Editar" class="btn-edit-curso" data-id="${curso.id_curso}" style="background:none; border:none; color:var(--texto-suave); font-size:18px; cursor:pointer;" onmouseover="this.style.color='var(--naranja)'" onmouseout="this.style.color='var(--texto-suave)'"><i class="ri-edit-line"></i></button>`;
        }
        if (p.edit && p.admin) {
            accionesHtml += `<button title="Eliminar" class="btn-delete-curso" data-id="${curso.id_curso}" style="background:none; border:none; color:var(--texto-suave); font-size:18px; cursor:pointer;" onmouseover="this.style.color='#d93025'" onmouseout="this.style.color='var(--texto-suave)'"><i class="ri-delete-bin-line"></i></button>`;
        }
        if (!p.edit) {
            accionesHtml += `<span style="color:var(--texto-suave); font-size: 12px;">N/A</span>`;
        }
        accionesHtml += `</div>`;

        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--borde)';
        tr.style.transition = 'background-color 0.2s';

        tr.innerHTML = `
            <td style="padding: 16px; font-size: 14px; color: var(--texto);">${curso.id_curso}</td>
            <td style="padding: 16px; font-size: 14px; color: var(--texto); font-weight: 500;">${curso.nombre}</td>
            <td style="padding: 16px;"><span class="badge ${badgeClass}">${nivelInfo.label}</span></td>
            <td style="padding: 16px; font-size: 14px; color: var(--texto);">${curso.duracion_semanas} Semanas</td>
            <td style="padding: 16px; font-size: 14px; color: var(--texto);">${costo}</td>
            <td style="padding: 16px; font-size: 14px; color: var(--texto);">${curso.nombre_sede || '-'}</td>
            <td style="padding: 16px;">${estatusHtml}</td>
            <td style="padding: 16px; text-align: center;">${accionesHtml}</td>
        `;

        tbodyCursos.appendChild(tr);
    }

    // Inicializar llamadas
    cargarGrupos();
    cargarCursos();

    // ==========================================
    // EVENTOS DE BÚSQUEDA (Debounce)
    // ==========================================
    const debounce = (func, delay) => {
        let timeoutId;
        return (...args) => {
            if (timeoutId) clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                func.apply(null, args);
            }, delay);
        };
    };

    const buscadorGrupos = document.getElementById('buscador-grupos');
    if (buscadorGrupos) {
        buscadorGrupos.addEventListener('input', debounce((e) => {
            cargarGrupos(e.target.value.trim());
        }, 350));
    }

    const buscadorCursos = document.getElementById('buscador-tabla-cursos');
    if (buscadorCursos) {
        buscadorCursos.addEventListener('input', debounce((e) => {
            cargarCursos(e.target.value.trim());
        }, 350));
    }

    // ==========================================
    // LÓGICA DEL MODAL CREAR GRUPO
    // ==========================================
    let idEditGrupo = null;
    const modalGrupo = document.getElementById('modal-agregar-grupo');
    const btnAgregarGrupo = document.getElementById('btn-agregar-grupo');
    const btnCloseGrupo = document.getElementById('modal-close-grupo');
    const btnCancelGrupo = document.getElementById('btn-cancelar-modal-grupo');

    const abrirModalGrupo = async () => {
        if (modalGrupo) {
            modalGrupo.classList.add('open');
            document.body.style.overflow = 'hidden';

            // Cargar selects
            const opCursos = document.getElementById('opciones-gr-curso');
            const opProf = document.getElementById('opciones-gr-instructor');
            const triggerCursos = document.querySelector('#trigger-gr-curso .selected-text');
            const triggerProf = document.querySelector('#trigger-gr-instructor .selected-text');

            if (triggerCursos) triggerCursos.textContent = 'Cargando cursos...';
            if (triggerProf) triggerProf.textContent = 'Cargando profesores...';

            try {
                // Fetch Cursos
                const respC = await fetch('/admin/cursos/buscar', { headers: { 'Authorization': `Bearer ${token}` } });
                const resC = await respC.json();
                if (opCursos) {
                    opCursos.innerHTML = '';
                    if (resC.data && resC.data.length > 0) {
                        resC.data.forEach(c => {
                            const opt = document.createElement('div');
                            opt.className = 'form-option';
                            opt.setAttribute('data-value', c.id_curso);
                            opt.textContent = `${c.nombre} (${c.nivel || 'N/A'})`;
                            opt.addEventListener('click', () => {
                                document.getElementById('gr-curso').value = c.id_curso;
                                triggerCursos.textContent = opt.textContent;
                                document.getElementById('dropdown-gr-curso').classList.remove('open');
                            });
                            opCursos.appendChild(opt);
                        });
                        if (triggerCursos) triggerCursos.textContent = 'Selecciona un curso...';
                    } else {
                        if (triggerCursos) triggerCursos.textContent = 'No hay cursos';
                    }
                }

                // Fetch Profesores
                const respP = await fetch('/admin/buscar/profesor', { headers: { 'Authorization': `Bearer ${token}` } });
                const resP = await respP.json();
                if (opProf) {
                    opProf.innerHTML = '';
                    if (resP.data && resP.data.length > 0) {
                        resP.data.forEach(p => {
                            const opt = document.createElement('div');
                            opt.className = 'form-option';
                            opt.setAttribute('data-value', p.id);
                            opt.textContent = `${p.nombre} ${p.apellido_p}`;
                            opt.addEventListener('click', () => {
                                document.getElementById('gr-instructor').value = p.id;
                                triggerProf.textContent = opt.textContent;
                                document.getElementById('dropdown-gr-instructor').classList.remove('open');
                            });
                            opProf.appendChild(opt);
                        });
                        if (triggerProf) triggerProf.textContent = 'Asignar un profesor...';
                    } else {
                        if (triggerProf) triggerProf.textContent = 'No hay profesores';
                    }
                }
            } catch (e) {
                console.error('Error al cargar opciones del modal', e);
            }
        }
    };

    const llenarModalGrupo = async (id) => {
        try {
            const resp = await fetch(`/admin/grupos/${id}`, { headers: { 'Authorization': `Bearer ${token}` } });
            const data = await resp.json();
            if (data.error) return alert(data.error);

            idEditGrupo = id;
            document.querySelector('#modal-agregar-grupo .modal-title').textContent = 'Editar Grupo';

            await abrirModalGrupo(); // carga sedes y profes, abre modal

            document.getElementById('gr-curso').value = data.id_curso;
            const triggerCursos = document.querySelector('#trigger-gr-curso .selected-text');
            if (triggerCursos) triggerCursos.textContent = `${data.nombre_curso} (${data.nivel})`;

            document.getElementById('gr-instructor').value = data.id_profesor;
            const triggerProf = document.querySelector('#trigger-gr-instructor .selected-text');
            if (triggerProf) triggerProf.textContent = data.nombre_profesor;

            document.querySelector('[name="codigo_grupo"]').value = data.codigo_grupo;
            document.querySelector('[name="nombre"]').value = data.nombre || '';
            elegirOpcion('dropdown-gr-periodo', data.periodo, 'Selecciona un periodo...');
            document.querySelector('[name="fecha_inicio"]').value = data.fecha_inicio;
            document.querySelector('[name="fecha_fin"]').value = data.fecha_fin;
            document.querySelector('[name="cupo_maximo"]').value = data.cupo_maximo;

            document.getElementById('gr-estatus-general').value = data.estatus ? 'activo' : 'inactivo';
            const triggerEst = document.querySelector('#trigger-gr-estatus .selected-text');
            if (triggerEst) triggerEst.textContent = data.estatus ? 'Activo' : 'Inactivo';

            // Llenar horarios
            const btnAddH = document.getElementById('btn-add-horario');
            if (data.horarios && data.horarios.length > 0) {
                data.horarios.forEach(h => {
                    if (btnAddH) btnAddH.click(); // Crea fila
                    const rows = document.querySelectorAll('.horario-row');
                    const lastRow = rows[rows.length - 1];
                    lastRow.querySelector('[name="horario_dia[]"]').value = h.dia_semana;
                    lastRow.querySelector('[name="horario_inicio[]"]').value = h.hora_inicio;
                    lastRow.querySelector('[name="horario_fin[]"]').value = h.hora_fin;
                    lastRow.querySelector('[name="horario_ubicacion[]"]').value = h.ubicacion || '';
                });
            }

        } catch (e) { console.error('Error al cargar grupo:', e); }
    };

    const cerrarModalGrupo = () => {
        if (modalGrupo) {
            modalGrupo.classList.remove('open');
            document.body.style.overflow = '';
            const form = document.getElementById('form-agregar-grupo');
            if (form) form.reset();
            // Reset dropdowns visualmente
            const triggerCursos = document.querySelector('#trigger-gr-curso .selected-text');
            const triggerProf = document.querySelector('#trigger-gr-instructor .selected-text');
            if (triggerCursos) triggerCursos.textContent = 'Selecciona un curso...';
            if (triggerProf) triggerProf.textContent = 'Asignar un profesor...';
            document.getElementById('gr-curso').value = '';
            document.getElementById('gr-instructor').value = '';
            elegirOpcion('dropdown-gr-periodo', '', 'Selecciona un periodo...');

            // Limpiar horarios
            const horariosList = document.getElementById('horarios-list');
            if (horariosList) horariosList.innerHTML = '';

            idEditGrupo = null;
            document.querySelector('#modal-agregar-grupo .modal-title').textContent = 'Crear Nuevo Grupo';
        }
    };

    if (btnAgregarGrupo) btnAgregarGrupo.addEventListener('click', () => { idEditGrupo = null; abrirModalGrupo(); });
    if (btnCloseGrupo) btnCloseGrupo.addEventListener('click', cerrarModalGrupo);
    if (btnCancelGrupo) btnCancelGrupo.addEventListener('click', cerrarModalGrupo);

    const formGrupo = document.getElementById('form-agregar-grupo');
    if (formGrupo) {
        formGrupo.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Recolectar datos
            const formData = new FormData(formGrupo);
            const estatusVal = formData.get('estatus');

            const data = {
                id_curso: parseInt(formData.get('id_curso')) || 0,
                id_profesor: parseInt(formData.get('id_instructor')) || parseInt(formData.get('id_profesor')) || 0,
                codigo_grupo: formData.get('codigo_grupo'),
                nombre: formData.get('nombre') || '',
                periodo: formData.get('periodo') || '',
                fecha_inicio: formData.get('fecha_inicio'),
                fecha_fin: formData.get('fecha_fin'),
                cupo_maximo: parseInt(formData.get('cupo_maximo')) || 0,
                estatus: (estatusVal === '1' || estatusVal === 'on' || estatusVal === 'activo' || estatusVal === 'true'),
                horarios: []
            };

            const hDias = formData.getAll('horario_dia[]');
            const hIni = formData.getAll('horario_inicio[]');
            const hFin = formData.getAll('horario_fin[]');
            const hUbi = formData.getAll('horario_ubicacion[]');

            for (let i = 0; i < hDias.length; i++) {
                data.horarios.push({
                    dia_semana: parseInt(hDias[i]) || 0,
                    hora_inicio: hIni[i],
                    hora_fin: hFin[i],
                    ubicacion: hUbi[i] || ''
                });
            }

            console.log("BODY A ENVIAR:", data);

            try {
                const url = idEditGrupo ? `/admin/grupos/${idEditGrupo}` : '/admin/grupos/registrar';
                const method = idEditGrupo ? 'PUT' : 'POST';
                const resp = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token}`, 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                    body: JSON.stringify(data)
                });
                const resData = await resp.json();

                if (resp.ok) {
                    cerrarModalGrupo();
                    cargarGrupos(); // Refrescar
                } else {
                    alert(resData.error || Object.values(resData.errors || {}).flat()[0] || 'Ocurrió un error (422)');
                }
            } catch (error) {
                console.error("Error al guardar:", error);
            }
        });
    }

    if (gridGrupos) {
        gridGrupos.addEventListener('click', async (e) => {
            const btnVer = e.target.closest('.btn-grupo-ver');
            if (btnVer) {
                mostrarDetalleGrupo(btnVer.getAttribute('data-id'));
            }
            const btnEdit = e.target.closest('.btn-grupo-editar');
            if (btnEdit) {
                llenarModalGrupo(btnEdit.getAttribute('data-id'));
            }
            const btnDel = e.target.closest('.btn-grupo-eliminar');
            if (btnDel) {
                const id = btnDel.getAttribute('data-id');
                if (confirm('¿Estás seguro de eliminar este grupo? Se perderán sus horarios.')) {
                    try {
                        const resp = await fetch(`/admin/grupos/${id}`, {
                            method: 'DELETE',
                            headers: { 'Authorization': `Bearer ${token}`, 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                        });
                        const resData = await resp.json();
                        if (resp.ok) cargarGrupos();
                        else alert(resData.error || 'Error al eliminar');
                    } catch (err) { console.error(err); }
                }
            }
        });
    }

    // ==========================================
    // MOSTRAR DETALLE GRUPO
    // ==========================================
    async function mostrarDetalleGrupo(id) {
        window.location.href = `/admin/grupos/${id}/ver`;
    }

    // ==========================================
    // LÓGICA DE HORARIOS DINÁMICOS
    // ==========================================
    const btnAddHorario = document.getElementById('btn-add-horario');
    const horariosList = document.getElementById('horarios-list');

    if (btnAddHorario && horariosList) {
        btnAddHorario.addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'horario-row';
            row.style.display = 'flex';
            row.style.gap = '12px';
            row.style.marginBottom = '12px';
            row.style.alignItems = 'center';

            row.innerHTML = `
                <select name="horario_dia[]" style="padding:10px; border:1px solid var(--borde); border-radius:8px; font-family:'Inter';" required>
                    <option value="" disabled selected>Día</option>
                    <option value="1">Lunes</option>
                    <option value="2">Martes</option>
                    <option value="3">Miércoles</option>
                    <option value="4">Jueves</option>
                    <option value="5">Viernes</option>
                    <option value="6">Sábado</option>
                    <option value="7">Domingo</option>
                </select>
                <input type="time" name="horario_inicio[]" style="padding:10px; border:1px solid var(--borde); border-radius:8px; font-family:'Inter';" required>
                <span style="color:var(--texto-suave);">a</span>
                <input type="time" name="horario_fin[]" style="padding:10px; border:1px solid var(--borde); border-radius:8px; font-family:'Inter';" required>
                <input type="text" name="horario_ubicacion[]" placeholder="Ubicación (Opcional)" style="padding:10px; border:1px solid var(--borde); border-radius:8px; font-family:'Inter'; flex-grow:1;">
                <button type="button" class="btn-remove-horario" title="Eliminar fila" style="background:#fce8e6; color:#d93025; border:none; border-radius:8px; width:40px; height:40px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                    <i class="ri-delete-bin-line"></i>
                </button>
            `;

            const btnRemove = row.querySelector('.btn-remove-horario');
            btnRemove.addEventListener('click', () => {
                row.remove();
            });

            horariosList.appendChild(row);
        });
    }

    // ==========================================
    // LÓGICA DEL MODAL CREAR CURSO
    // ==========================================
    let idEditCurso = null;
    const modalCurso = document.getElementById('modal-agregar-curso');
    const btnAgregarCurso = document.getElementById('btn-agregar-curso');
    const btnCloseCurso = document.getElementById('modal-close-curso');
    const btnCancelCurso = document.getElementById('btn-cancelar-modal-curso');

    const abrirModalCurso = async () => {
        if (modalCurso) {
            modalCurso.classList.add('open');
            document.body.style.overflow = 'hidden';

            // Cargar Sedes
            const opSedes = document.getElementById('opciones-cu-sede');
            const triggerSedes = document.querySelector('#trigger-cu-sede .selected-text');
            if (triggerSedes) triggerSedes.textContent = 'Cargando sedes...';

            try {
                const respS = await fetch('/admin/buscar/sedes', { headers: { 'Authorization': `Bearer ${token}` } });
                const resS = await respS.json();

                if (opSedes) {
                    opSedes.innerHTML = '';
                    if (resS.data && resS.data.length > 0) {
                        resS.data.forEach(s => {
                            const opt = document.createElement('div');
                            opt.className = 'form-option';
                            opt.setAttribute('data-value', s.id);
                            opt.textContent = s.nombre;
                            opt.addEventListener('click', () => {
                                document.getElementById('cu-sede').value = s.id;
                                triggerSedes.textContent = opt.textContent;
                                document.getElementById('dropdown-cu-sede').classList.remove('open');
                            });
                            opSedes.appendChild(opt);
                        });
                        if (triggerSedes) triggerSedes.textContent = 'Selecciona una sede...';
                    } else {
                        if (triggerSedes) triggerSedes.textContent = 'No hay sedes';
                    }
                }
            } catch (e) {
                console.error('Error al cargar sedes', e);
            }
        }
    };

    const llenarModalCurso = async (id) => {
        try {
            const resp = await fetch(`/admin/cursos/${id}`, { headers: { 'Authorization': `Bearer ${token}` } });
            const data = await resp.json();
            if (data.error) return alert(data.error);

            idEditCurso = id;
            document.querySelector('#modal-agregar-curso .modal-title').textContent = 'Editar Curso';

            await abrirModalCurso();

            document.querySelector('#form-agregar-curso [name="nombre"]').value = data.nombre;
            document.querySelector('#form-agregar-curso [name="duracion_semanas"]').value = data.duracion_semanas;
            document.querySelector('#form-agregar-curso [name="horas_totales"]').value = data.horas_totales;
            document.querySelector('#form-agregar-curso [name="costo_base"]').value = data.costo_base;
            document.querySelector('#form-agregar-curso [name="descripcion"]').value = data.descripcion || '';
            document.querySelector('#form-agregar-curso [name="requisitos"]').value = data.requisitos || '';

            document.getElementById('cu-sede').value = data.id_sede;
            const triggerS = document.querySelector('#trigger-cu-sede .selected-text');
            if (triggerS) triggerS.textContent = data.nombre_sede || 'Sede asignada';

            elegirOpcion('dropdown-cu-nivel', data.nivel, 'Selecciona un nivel...');

            document.getElementById('cu-estatus').value = data.estatus ? '1' : '0';
            const triggerE = document.querySelector('#trigger-cu-estatus .selected-text');
            if (triggerE) triggerE.textContent = data.estatus ? 'Activo' : 'Inactivo';

        } catch (e) { console.error('Error al cargar curso:', e); }
    };

    const cerrarModalCurso = () => {
        if (modalCurso) {
            modalCurso.classList.remove('open');
            document.body.style.overflow = '';
            const form = document.getElementById('form-agregar-curso');
            if (form) form.reset();

            // Reset dropdowns visualmente
            const triggerSedes = document.querySelector('#trigger-cu-sede .selected-text');
            const triggerNivel = document.querySelector('#trigger-cu-nivel .selected-text');
            const triggerEstatus = document.querySelector('#trigger-cu-estatus .selected-text');
            if (triggerSedes) triggerSedes.textContent = 'Selecciona una sede...';
            if (triggerNivel) triggerNivel.textContent = 'Selecciona un nivel...';
            if (triggerEstatus) triggerEstatus.textContent = 'Activo';

            document.getElementById('cu-sede').value = '';
            document.getElementById('cu-nivel').value = '';
            document.getElementById('cu-estatus').value = '1';

            idEditCurso = null;
            document.querySelector('#modal-agregar-curso .modal-title').textContent = 'Crear Nuevo Curso';
        }
    };

    if (btnAgregarCurso) btnAgregarCurso.addEventListener('click', () => { idEditCurso = null; abrirModalCurso(); });
    if (btnCloseCurso) btnCloseCurso.addEventListener('click', cerrarModalCurso);
    if (btnCancelCurso) btnCancelCurso.addEventListener('click', cerrarModalCurso);

    const formCurso = document.getElementById('form-agregar-curso');
    if (formCurso) {
        formCurso.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(formCurso);
            const estatusVal = formData.get('estatus');

            const data = {
                nombre: formData.get('nombre'),
                id_sede: parseInt(formData.get('id_sede')) || parseInt(document.getElementById('cu-sede').value) || 0,
                nivel: formData.get('nivel') || document.getElementById('cu-nivel').value || '',
                duracion_semanas: parseInt(formData.get('duracion_semanas')) || 0,
                horas_totales: parseInt(formData.get('horas_totales')) || 0,
                costo_base: parseFloat(formData.get('costo_base')) || 0,
                descripcion: formData.get('descripcion') || '',
                estatus: (estatusVal === '1' || estatusVal === 'on' || estatusVal === 'activo' || estatusVal === 'true' || estatusVal === true)
            };

            console.log("BODY CURSO A ENVIAR:", data);

            try {
                const url = idEditCurso ? `/admin/cursos/${idEditCurso}` : '/admin/cursos/registrar';
                const method = idEditCurso ? 'PUT' : 'POST';
                const resp = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token}`, 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                    body: JSON.stringify(data)
                });
                const resData = await resp.json();

                if (resp.ok) {
                    cerrarModalCurso();
                    cargarCursos(); // Refrescar
                } else {
                    alert(resData.error || Object.values(resData.errors || {}).flat()[0] || 'Ocurrió un error (422)');
                }
            } catch (error) {
                console.error("Error al guardar:", error);
            }
        });
    }


    if (tbodyCursos) {
        tbodyCursos.addEventListener('click', async (e) => {
            const btnVer = e.target.closest('.btn-ver-curso');
            if (btnVer) {
                const id = btnVer.getAttribute('data-id');
                const modal = document.getElementById('modal-ver-curso');
                if (modal) { modal.classList.add('open'); document.body.style.overflow = 'hidden'; }
                fetch(`/admin/cursos/${id}`, { headers: { 'Authorization': `Bearer ${token}` } })
                    .then(r => r.json())
                    .then(c => {
                        const setV = (elId, val) => { const el = document.getElementById(elId); if (el) el.textContent = val || '—'; };
                        const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
                        setV('vcu-nombre', c.nombre);
                        setV('vcu-sede', c.nombre_sede || '—');
                        setV('vcu-nivel', getNivelLabel(c.nivel).label);
                        setV('vcu-duracion', c.duracion_semanas ? c.duracion_semanas + ' semanas' : '—');
                        setV('vcu-horas', c.horas_totales ? c.horas_totales + ' hrs' : '—');
                        setV('vcu-costo', formatter.format(c.costo_base || 0));
                        setV('vcu-estatus', c.estatus ? 'Activo' : 'Inactivo');
                        setV('vcu-descripcion', c.descripcion || '—');
                        setV('vcu-requisitos', c.requisitos || '—');
                    })
                    .catch(err => console.error('Error al cargar curso:', err));
            }

            const btnEdit = e.target.closest('.btn-edit-curso');
            if (btnEdit) {
                llenarModalCurso(btnEdit.getAttribute('data-id'));
            }

            const btnDel = e.target.closest('.btn-delete-curso');
            if (btnDel) {
                const id = btnDel.getAttribute('data-id');
                if (confirm('¿Estás seguro de eliminar este curso? No podrá deshacerse.')) {
                    try {
                        const resp = await fetch(`/admin/cursos/${id}`, {
                            method: 'DELETE',
                            headers: { 'Authorization': `Bearer ${token}`, 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
                        });
                        const resData = await resp.json();
                        if (resp.ok) cargarCursos();
                        else alert(resData.error || 'Error al eliminar');
                    } catch (err) { console.error(err); }
                }
            }
        });
    }

    document.querySelectorAll('#modal-agregar-grupo .form-dropdown .form-select-trigger, #modal-agregar-curso .form-dropdown .form-select-trigger').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const parent = trigger.parentElement;
            document.querySelectorAll('.form-dropdown').forEach(d => {
                if (d !== parent) d.classList.remove('open');
            });
            parent.classList.toggle('open');
        });
    });

    // Lógica genérica de selección para dropdowns estáticos (Estatus, Nivel)
    document.querySelectorAll('.form-dropdown .form-options-container .form-option').forEach(option => {
        // Solo para los que no se llenan dinámicamente
        if (!option.parentElement.id || (!option.parentElement.id.includes('opciones-gr') && !option.parentElement.id.includes('opciones-cu-sede'))) {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                const container = option.parentElement;
                const dropdown = container.parentElement;
                const trigger = dropdown.querySelector('.selected-text');
                const hiddenInput = dropdown.querySelector('input[type="hidden"]');

                // Actualizar valor
                hiddenInput.value = option.getAttribute('data-value');
                trigger.textContent = option.textContent;

                // Actualizar clase selected
                container.querySelectorAll('.form-option').forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');

                dropdown.classList.remove('open');
            });
        }
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('.form-dropdown').forEach(d => d.classList.remove('open'));
    });

    const modalVerCurso = document.getElementById('modal-ver-curso');
    const cerrarVerCurso = () => { if (modalVerCurso) { modalVerCurso.classList.remove('open'); document.body.style.overflow = ''; } };
    const btnCerrarVerCurso = document.getElementById('btn-cerrar-ver-curso');
    const btnCloseVerCurso = document.getElementById('modal-close-ver-curso');
    if (btnCerrarVerCurso) btnCerrarVerCurso.addEventListener('click', cerrarVerCurso);
    if (btnCloseVerCurso) btnCloseVerCurso.addEventListener('click', cerrarVerCurso);
    if (modalVerCurso) modalVerCurso.addEventListener('click', (e) => { if (e.target === modalVerCurso) cerrarVerCurso(); });

});