// ==============================================
//  dashboardAdminBiblioteca.js — Biblioteca Virtual
//  Catálogo, Detalle, Partidas, Visor, Guardados,
//  Historial y Gestión de material (SCRUM-18)
//  EGAU Chess | AMAAC
//
//  Visible para todo el personal, sin depender de permisos.
//  Por ahora usa los datos de prueba de
//  dashboardAdminBibliotecaDatos.js; "Mis guardados" e
//  "Historial" se guardan en localStorage.
// ==============================================

document.addEventListener('DOMContentLoaded', () => {

    const seccion = document.getElementById('section-biblioteca');
    if (!seccion || !window.BIBLIOTECA_DATOS) return;

    const { NIVELES, TIPOS, PORTADAS, partidas, guardadosIniciales, historialInicial } = window.BIBLIOTECA_DATOS;
    let documentos = window.BIBLIOTECA_DATOS.documentos.map(d => ({ ...d }));

    const PAGE_SIZE = 6;
    const LS_GUARDADOS = 'egau_bib_guardados';
    const LS_HISTORIAL = 'egau_bib_historial';

    const $ = (sel) => seccion.querySelector(sel);
    const $$ = (sel) => seccion.querySelectorAll(sel);

    // ── Utilidades ─────────────────────────────────
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);

    const nivelInfo = (n) => NIVELES.find(x => x.nombre === n) || NIVELES[0];
    const tipoInfo = (t) => TIPOS.find(x => x.nombre === t) || TIPOS[0];

    const fechaLarga = (f) => new Date(f + 'T00:00:00').toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
    const fechaCorta = (f) => new Date(f + 'T00:00:00').toLocaleDateString('es-MX');
    const fechaMedia = (f) => new Date(f + 'T00:00:00').toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });

    const badgeNivel = (n) => {
        const ni = nivelInfo(n);
        return `<span class="bib-badge ${ni.clase}">${ni.icono} ${esc(ni.nombre)}</span>`;
    };
    const badgeTipo = (t) => `<span class="bib-chip ${tipoInfo(t).clase}">${esc(t)}</span>`;

    const leerLS = (clave, porDefecto) => {
        try {
            const v = localStorage.getItem(clave);
            return v === null ? porDefecto : JSON.parse(v);
        } catch { return porDefecto; }
    };
    const escribirLS = (clave, valor) => {
        try { localStorage.setItem(clave, JSON.stringify(valor)); } catch { /* sin almacenamiento */ }
    };

    const alerta = (msg, tipo = 'success') => {
        if (window.egauAlert) window.egauAlert(msg, tipo);
    };

    let guardados = leerLS(LS_GUARDADOS, guardadosIniciales);
    let historial = leerLS(LS_HISTORIAL, historialInicial);

    // ── Navegación interna (pestañas y vistas) ─────
    // Vistas: catalogo, documento, partidas, visor, guardados, historial, gestion
    const PESTANA_DE_VISTA = { documento: 'catalogo', visor: 'partidas' };
    let vistaAnterior = 'catalogo';

    function mostrarVista(vista) {
        $$('.bib-vista').forEach(v => { v.style.display = v.dataset.vista === vista ? 'block' : 'none'; });
        const pestana = PESTANA_DE_VISTA[vista] || vista;
        $$('.bib-tab').forEach(t => t.classList.toggle('active', t.dataset.vista === pestana));
        seccion.scrollIntoView({ block: 'start' });
    }

    $$('.bib-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const v = tab.dataset.vista;
            if (v === 'catalogo') renderCatalogo();
            if (v === 'partidas') renderPartidas();
            if (v === 'guardados') renderGuardados();
            if (v === 'historial') renderHistorial();
            if (v === 'gestion') renderGestion();
            mostrarVista(v);
        });
    });

    // Botones genéricos de navegación (volver, explorar catálogo…)
    seccion.addEventListener('click', (e) => {
        const ir = e.target.closest('[data-ir]');
        if (ir) {
            e.preventDefault();
            const destino = ir.dataset.ir === 'anterior' ? vistaAnterior : ir.dataset.ir;
            seccion.querySelector(`.bib-tab[data-vista="${destino}"]`)?.click();
            return;
        }
        const verDoc = e.target.closest('[data-ver-doc]');
        if (verDoc) {
            e.preventDefault();
            abrirDocumento(Number(verDoc.dataset.verDoc));
            return;
        }
        const verPartida = e.target.closest('[data-ver-partida]');
        if (verPartida) {
            e.preventDefault();
            abrirVisor(Number(verPartida.dataset.verPartida));
        }
    });

    // ── Selects de filtros ─────────────────────────
    function llenarSelect(sel, opciones) {
        sel.innerHTML += opciones.map(o => `<option value="${esc(o.valor)}">${esc(o.texto)}</option>`).join('');
    }
    const opcionesNivel = NIVELES.map(n => ({ valor: n.nombre, texto: `${n.icono} ${n.nombre}` }));
    const opcionesTipo = TIPOS.map(t => ({ valor: t.nombre, texto: t.nombre }));
    const anios = [...new Set(documentos.map(d => d.fecha.slice(0, 4)))].sort();

    llenarSelect($('#bib-filtro-nivel'), opcionesNivel);
    llenarSelect($('#bib-filtro-tipo'), opcionesTipo);
    llenarSelect($('#bib-filtro-anio'), anios.map(a => ({ valor: a, texto: a })));
    llenarSelect($('#bib-partidas-nivel'), opcionesNivel);
    llenarSelect(document.getElementById('bib-form-tipo'), opcionesTipo);
    llenarSelect(document.getElementById('bib-form-nivel'), opcionesNivel);

    // =============================================
    //  CATÁLOGO
    // =============================================
    let pagina = 1;

    function tarjetaDocumento(doc) {
        const ni = nivelInfo(doc.nivel);
        return `
        <a href="#" class="bib-card" data-ver-doc="${doc.id}">
            <div class="bib-card-portada ${doc.portada}">
                <span class="bib-card-marca">${ni.icono}</span>
                <i class="${tipoInfo(doc.tipo).icono} bib-card-icono"></i>
                <span class="bib-card-tipo">${badgeTipo(doc.tipo)}</span>
            </div>
            <div class="bib-card-body">
                <h3 class="bib-card-titulo">${esc(doc.titulo)}</h3>
                <p class="bib-card-creador">${esc(doc.creador)}</p>
                <div class="bib-card-pie">
                    ${badgeNivel(doc.nivel)}
                    <span class="bib-texto-suave">${esc(doc.formato)}</span>
                </div>
            </div>
        </a>`;
    }

    function renderCatalogo() {
        const q = $('#bib-buscar').value.trim().toLowerCase();
        const nivel = $('#bib-filtro-nivel').value;
        const tipo = $('#bib-filtro-tipo').value;
        const anio = $('#bib-filtro-anio').value;

        const filtrados = documentos.filter(d =>
            d.estatus === 'Activo' &&
            (!q || d.titulo.toLowerCase().includes(q) || d.creador.toLowerCase().includes(q) || d.tema.toLowerCase().includes(q)) &&
            (!nivel || d.nivel === nivel) &&
            (!tipo || d.tipo === tipo) &&
            (!anio || d.fecha.startsWith(anio))
        );

        const totalPaginas = Math.max(1, Math.ceil(filtrados.length / PAGE_SIZE));
        if (pagina > totalPaginas) pagina = totalPaginas;
        const visibles = filtrados.slice((pagina - 1) * PAGE_SIZE, pagina * PAGE_SIZE);

        const n = filtrados.length;
        $('#bib-conteo').textContent = `${n} documento${n !== 1 ? 's' : ''} encontrado${n !== 1 ? 's' : ''}`;

        $('#bib-grid').innerHTML = visibles.length
            ? visibles.map(tarjetaDocumento).join('')
            : `<div class="bib-vacio-texto">No se encontraron documentos con los filtros seleccionados.</div>`;

        const pag = $('#bib-paginacion');
        if (totalPaginas <= 1) { pag.innerHTML = ''; return; }
        let html = `<button class="bib-btn-outline bib-btn-sm" data-pag="${pagina - 1}" ${pagina === 1 ? 'disabled' : ''}>Anterior</button>`;
        for (let p = 1; p <= totalPaginas; p++) {
            html += `<button class="bib-btn-sm ${p === pagina ? 'bib-btn-activo' : 'bib-btn-outline'}" data-pag="${p}">${p}</button>`;
        }
        html += `<button class="bib-btn-outline bib-btn-sm" data-pag="${pagina + 1}" ${pagina === totalPaginas ? 'disabled' : ''}>Siguiente</button>`;
        pag.innerHTML = html;
    }

    $('#bib-paginacion').addEventListener('click', (e) => {
        const b = e.target.closest('[data-pag]');
        if (!b || b.disabled) return;
        pagina = Number(b.dataset.pag);
        renderCatalogo();
    });

    $('#bib-buscar').addEventListener('input', () => { pagina = 1; renderCatalogo(); });
    ['#bib-filtro-nivel', '#bib-filtro-tipo', '#bib-filtro-anio'].forEach(id =>
        $(id).addEventListener('change', () => { pagina = 1; renderCatalogo(); })
    );

    // =============================================
    //  DETALLE DE DOCUMENTO
    // =============================================
    let docActual = null;

    function abrirDocumento(id) {
        const doc = documentos.find(d => d.id === id);
        if (!doc) return;

        const vistaActual = [...$$('.bib-vista')].find(v => v.style.display === 'block')?.dataset.vista;
        if (vistaActual && vistaActual !== 'documento') vistaAnterior = PESTANA_DE_VISTA[vistaActual] || vistaActual;
        $('#bib-doc-volver-texto').textContent = {
            catalogo: 'Volver al catálogo', guardados: 'Volver a mis guardados',
            historial: 'Volver al historial', gestion: 'Volver a gestión de material',
        }[vistaAnterior] || 'Volver';

        docActual = doc;
        registrarHistorial(id);

        const ni = nivelInfo(doc.nivel);
        $('#bib-doc-portada').className = `bib-doc-portada ${doc.portada}`;
        $('#bib-doc-portada').innerHTML = `<span class="bib-doc-marca">${ni.icono}</span>${badgeTipo(doc.tipo)}`;
        $('#bib-doc-titulo').textContent = doc.titulo;
        $('#bib-doc-creador').textContent = doc.creador;
        $('#bib-doc-nivel').innerHTML = badgeNivel(doc.nivel);

        const metadatos = [
            ['Título', doc.titulo],
            ['Creador', doc.creador],
            ['Tema', doc.tema],
            ['Descripción', doc.descripcion],
            ['Fecha', fechaLarga(doc.fecha)],
            ['Tipo', doc.tipo],
            ['Formato', doc.formato],
            ['Idioma', doc.idioma],
            ['Nivel', `${ni.icono} ${ni.nombre} (${ni.puntos}+ pts)`],
            ['Palabras clave', doc.palabrasClave.join(', ')],
            ['Tamaño', doc.tamano],
        ];
        $('#bib-doc-meta').innerHTML = metadatos.map(([k, v]) =>
            `<tr><td>${esc(k)}</td><td>${esc(v)}</td></tr>`).join('');

        pintarBotonGuardar();

        const relacionados = documentos
            .filter(d => d.id !== doc.id && d.estatus === 'Activo' && (d.nivel === doc.nivel || d.tipo === doc.tipo))
            .slice(0, 3);
        $('#bib-relacionados-bloque').style.display = relacionados.length ? 'block' : 'none';
        $('#bib-relacionados').innerHTML = relacionados.map(rel => `
            <a href="#" class="bib-card bib-card-mini" data-ver-doc="${rel.id}">
                <div class="bib-card-portada ${rel.portada}">
                    <span class="bib-card-marca">${nivelInfo(rel.nivel).icono}</span>
                </div>
                <div class="bib-card-body">
                    <h3 class="bib-card-titulo">${esc(rel.titulo)}</h3>
                    <p class="bib-card-creador">${esc(rel.creador)}</p>
                    <div class="bib-card-pie">${badgeNivel(rel.nivel)}${badgeTipo(rel.tipo)}</div>
                </div>
            </a>`).join('');

        mostrarVista('documento');
    }

    function pintarBotonGuardar() {
        const btn = $('#bib-doc-guardar');
        const esta = docActual && guardados.includes(docActual.id);
        btn.classList.toggle('bib-btn-guardado', !!esta);
        btn.innerHTML = esta
            ? '<i class="ri-bookmark-fill"></i> Guardado'
            : '<i class="ri-bookmark-line"></i> Guardar';
    }

    $('#bib-doc-guardar').addEventListener('click', () => {
        if (!docActual) return;
        if (guardados.includes(docActual.id)) {
            guardados = guardados.filter(id => id !== docActual.id);
            alerta('Documento quitado de tus guardados', 'warning');
        } else {
            guardados = [docActual.id, ...guardados];
            alerta('Documento guardado');
        }
        escribirLS(LS_GUARDADOS, guardados);
        pintarBotonGuardar();
    });

    ['#bib-doc-ver', '#bib-doc-descargar'].forEach(id =>
        $(id).addEventListener('click', () =>
            alerta('Disponible cuando se conecte el almacenamiento de archivos', 'warning'))
    );

    // =============================================
    //  MIS GUARDADOS e HISTORIAL
    // =============================================
    function filaLista(doc, extra, accionQuitar) {
        const ni = nivelInfo(doc.nivel);
        return `
        <div class="bib-fila">
            <div class="bib-fila-miniatura ${doc.portada}">${ni.icono}</div>
            <div class="bib-fila-info">
                <p class="bib-fila-titulo">${esc(doc.titulo)}</p>
                <p class="bib-texto-suave">${esc(doc.creador)}</p>
                <div class="bib-fila-etiquetas">${badgeNivel(doc.nivel)}${badgeTipo(doc.tipo)}${extra}</div>
            </div>
            <div class="bib-fila-acciones">
                <button class="bib-btn-outline bib-btn-sm" data-ver-doc="${doc.id}"><i class="ri-eye-line"></i> Ver</button>
                <button class="bib-btn-peligro bib-btn-sm" ${accionQuitar}><i class="ri-delete-bin-line"></i> Quitar</button>
            </div>
        </div>`;
    }

    function renderGuardados() {
        const docs = guardados.map(id => documentos.find(d => d.id === id)).filter(Boolean);
        $('#bib-guardados-vacio').style.display = docs.length ? 'none' : 'block';
        $('#bib-guardados-lista').style.display = docs.length ? 'block' : 'none';
        $('#bib-guardados-lista').innerHTML = docs.map(doc =>
            filaLista(doc, `<span class="bib-texto-suave">${esc(doc.formato)}</span>`, `data-quitar-guardado="${doc.id}"`)
        ).join('');
    }

    $('#bib-guardados-lista').addEventListener('click', (e) => {
        const b = e.target.closest('[data-quitar-guardado]');
        if (!b) return;
        guardados = guardados.filter(id => id !== Number(b.dataset.quitarGuardado));
        escribirLS(LS_GUARDADOS, guardados);
        renderGuardados();
    });

    function registrarHistorial(id) {
        const hoy = new Date();
        const f = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
        historial = [{ id, fechaConsulta: f }, ...historial.filter(h => h.id !== id)].slice(0, 50);
        escribirLS(LS_HISTORIAL, historial);
    }

    function renderHistorial() {
        const items = historial
            .map(h => ({ doc: documentos.find(d => d.id === h.id), fecha: h.fechaConsulta }))
            .filter(x => x.doc);
        $('#bib-historial-vacio').style.display = items.length ? 'none' : 'block';
        $('#bib-historial-lista').style.display = items.length ? 'block' : 'none';
        $('#bib-historial-limpiar').style.display = items.length ? 'flex' : 'none';
        $('#bib-historial-lista').innerHTML = items.map(({ doc, fecha }) =>
            filaLista(doc, `<span class="bib-texto-suave"><i class="ri-time-line"></i> ${esc(fechaMedia(fecha))}</span>`,
                `data-quitar-historial="${doc.id}"`)
        ).join('');
    }

    $('#bib-historial-lista').addEventListener('click', (e) => {
        const b = e.target.closest('[data-quitar-historial]');
        if (!b) return;
        historial = historial.filter(h => h.id !== Number(b.dataset.quitarHistorial));
        escribirLS(LS_HISTORIAL, historial);
        renderHistorial();
    });

    $('#bib-historial-limpiar').addEventListener('click', () => {
        historial = [];
        escribirLS(LS_HISTORIAL, historial);
        renderHistorial();
    });

    // =============================================
    //  PARTIDAS
    // =============================================
    const RESULTADO_CLASE = { '1-0': 'bib-res-blancas', '0-1': 'bib-res-negras', '½-½': 'bib-res-tablas' };

    function renderPartidas() {
        const q = $('#bib-partidas-buscar').value.trim().toLowerCase();
        const nivel = $('#bib-partidas-nivel').value;
        const filtradas = partidas.filter(p =>
            (!q || [p.blancas, p.negras, p.apertura, p.eco].some(x => x.toLowerCase().includes(q))) &&
            (!nivel || p.nivel === nivel)
        );
        $('#bib-partidas-tbody').innerHTML = filtradas.length
            ? filtradas.map(p => `
                <tr>
                    <td class="bib-negrita">${esc(p.blancas)}</td>
                    <td>${esc(p.negras)}</td>
                    <td><span class="bib-resultado ${RESULTADO_CLASE[p.resultado] || ''}">${esc(p.resultado)}</span></td>
                    <td>${esc(p.apertura)} <span class="bib-eco">${esc(p.eco)}</span></td>
                    <td>${badgeNivel(p.nivel)}</td>
                    <td class="bib-texto-suave">${esc(fechaCorta(p.fecha))}</td>
                    <td class="bib-derecha">
                        <button class="bib-btn-outline bib-btn-sm" data-ver-partida="${p.id}"><i class="ri-eye-line"></i> Ver</button>
                    </td>
                </tr>`).join('')
            : `<tr><td colspan="7" class="bib-vacio-texto">No se encontraron partidas.</td></tr>`;
    }

    $('#bib-partidas-buscar').addEventListener('input', renderPartidas);
    $('#bib-partidas-nivel').addEventListener('change', renderPartidas);

    // =============================================
    //  VISOR DE PARTIDA
    // =============================================
    // Tablero: arreglo 8x8, fila 0 = rango 8. Mayúsculas = blancas.
    const PIEZAS = {
        K: '♔', Q: '♕', R: '♖', B: '♗', N: '♘', P: '♙',
        k: '♚', q: '♛', r: '♜', b: '♝', n: '♞', p: '♟',
    };
    // Notación española → letra interna
    const ES_A_PIEZA = { R: 'K', D: 'Q', T: 'R', A: 'B', C: 'N' };

    const tableroInicial = () => [
        ['r', 'n', 'b', 'q', 'k', 'b', 'n', 'r'],
        ['p', 'p', 'p', 'p', 'p', 'p', 'p', 'p'],
        [null, null, null, null, null, null, null, null],
        [null, null, null, null, null, null, null, null],
        [null, null, null, null, null, null, null, null],
        [null, null, null, null, null, null, null, null],
        ['P', 'P', 'P', 'P', 'P', 'P', 'P', 'P'],
        ['R', 'N', 'B', 'Q', 'K', 'B', 'N', 'R'],
    ];

    const casilla = (s) => ({ f: s.charCodeAt(0) - 97, r: 8 - Number(s[1]) });
    const esBlanca = (p) => p && p === p.toUpperCase();

    function alcanza(tab, tipo, desde, hasta) {
        const df = hasta.f - desde.f, dr = hasta.r - desde.r;
        const adf = Math.abs(df), adr = Math.abs(dr);
        const libre = (pf, pr) => {
            const sf = Math.sign(pf), sr = Math.sign(pr);
            let f = desde.f + sf, r = desde.r + sr;
            while (f !== hasta.f || r !== hasta.r) {
                if (tab[r][f]) return false;
                f += sf; r += sr;
            }
            return true;
        };
        switch (tipo) {
            case 'N': return (adf === 1 && adr === 2) || (adf === 2 && adr === 1);
            case 'K': return Math.max(adf, adr) === 1;
            case 'R': return (df === 0 || dr === 0) && libre(df, dr);
            case 'B': return adf === adr && adf > 0 && libre(df, dr);
            case 'Q': return (df === 0 || dr === 0 || adf === adr) && libre(df, dr);
        }
        return false;
    }

    function reyEnJaque(tab, blancas) {
        const rey = blancas ? 'K' : 'k';
        let pos = null;
        tab.forEach((fila, r) => fila.forEach((p, f) => { if (p === rey) pos = { f, r }; }));
        if (!pos) return false;
        for (let r = 0; r < 8; r++) for (let f = 0; f < 8; f++) {
            const p = tab[r][f];
            if (!p || esBlanca(p) === blancas) continue;
            const t = p.toUpperCase();
            if (t === 'P') {
                const dir = esBlanca(p) ? -1 : 1;
                if (pos.r - r === dir && Math.abs(pos.f - f) === 1) return true;
            } else if (alcanza(tab, t, { f, r }, pos)) return true;
        }
        return false;
    }

    // Aplica una jugada en notación española; regresa el nuevo estado o null si no se pudo leer.
    function aplicarJugada(estado, san, turnoBlancas) {
        const tab = estado.tab.map(f => f.slice());
        let ep = null;
        const jug = san.replace(/[+#!?]/g, '').trim();
        const fila = turnoBlancas ? 7 : 0;

        if (/^(0-0-0|O-O-O)$/.test(jug) || /^(0-0|O-O)$/.test(jug)) {
            const largo = jug.length > 3;
            const rey = turnoBlancas ? 'K' : 'k', torre = turnoBlancas ? 'R' : 'r';
            tab[fila][4] = null;
            if (largo) { tab[fila][0] = null; tab[fila][2] = rey; tab[fila][3] = torre; }
            else { tab[fila][7] = null; tab[fila][6] = rey; tab[fila][5] = torre; }
            return { tab, ep, desde: { f: 4, r: fila }, hasta: { f: largo ? 2 : 6, r: fila } };
        }

        const m = jug.match(/^([RDTAC])?([a-h])?([1-8])?x?([a-h][1-8])(?:=?([DTAC]))?$/);
        if (!m) return null;
        const tipo = m[1] ? ES_A_PIEZA[m[1]] : 'P';
        const hasta = casilla(m[4]);
        const pieza = turnoBlancas ? tipo : tipo.toLowerCase();

        const candidatos = [];
        for (let r = 0; r < 8; r++) for (let f = 0; f < 8; f++) {
            if (tab[r][f] !== pieza) continue;
            if (m[2] && f !== m[2].charCodeAt(0) - 97) continue;
            if (m[3] && r !== 8 - Number(m[3])) continue;
            const desde = { f, r };
            if (tipo === 'P') {
                const dir = turnoBlancas ? -1 : 1;
                const destino = tab[hasta.r][hasta.f];
                const captura = Math.abs(hasta.f - f) === 1 && hasta.r - r === dir &&
                    ((destino && esBlanca(destino) !== turnoBlancas) ||
                        (estado.ep && estado.ep.f === hasta.f && estado.ep.r === hasta.r));
                const avance = hasta.f === f && !destino && (
                    hasta.r - r === dir ||
                    (hasta.r - r === 2 * dir && r === (turnoBlancas ? 6 : 1) && !tab[r + dir][f]));
                if (captura || avance) candidatos.push(desde);
            } else if (alcanza(tab, tipo, desde, hasta)) {
                candidatos.push(desde);
            }
        }

        // Si hay más de uno, descartar el que deja al rey en jaque (pieza clavada)
        const validos = candidatos.filter(d => {
            const prueba = tab.map(f => f.slice());
            prueba[hasta.r][hasta.f] = prueba[d.r][d.f];
            prueba[d.r][d.f] = null;
            return !reyEnJaque(prueba, turnoBlancas);
        });
        const desde = validos[0];
        if (!desde) return null;

        if (tipo === 'P') {
            if (desde.f !== hasta.f && !tab[hasta.r][hasta.f]) tab[desde.r][hasta.f] = null; // al paso
            if (Math.abs(hasta.r - desde.r) === 2) ep = { f: desde.f, r: (desde.r + hasta.r) / 2 };
        }
        let nueva = tab[desde.r][desde.f];
        if (m[5]) nueva = turnoBlancas ? ES_A_PIEZA[m[5]] : ES_A_PIEZA[m[5]].toLowerCase();
        tab[hasta.r][hasta.f] = nueva;
        tab[desde.r][desde.f] = null;
        return { tab, ep, desde, hasta };
    }

    let partidaActual = null;
    let posiciones = [];   // posiciones[0] = inicial, posiciones[i] = tras la jugada i
    let movIndex = -1;

    function abrirVisor(id) {
        const p = partidas.find(x => x.id === id);
        if (!p) return;
        partidaActual = p;

        posiciones = [{ tab: tableroInicial(), ep: null }];
        for (let i = 0; i < p.movimientos.length; i++) {
            const sig = aplicarJugada(posiciones[i], p.movimientos[i], i % 2 === 0);
            if (!sig) {
                console.warn(`Biblioteca: no se pudo leer la jugada ${i + 1} (${p.movimientos[i]}) de la partida ${p.id}`);
                break;
            }
            posiciones.push(sig);
        }
        movIndex = -1;

        $('#bib-visor-titulo').textContent = `${p.blancas} vs ${p.negras}`;
        const ficha = [
            ['Blancas', esc(p.blancas)],
            ['Negras', esc(p.negras)],
            ['Resultado', `<span class="bib-resultado ${RESULTADO_CLASE[p.resultado] || ''}">${esc(p.resultado)}</span>`],
            ['Fecha', esc(fechaCorta(p.fecha))],
            ['Evento', esc(p.evento)],
            ['Apertura', esc(p.apertura)],
            ['ECO', `<span class="bib-eco">${esc(p.eco)}</span>`],
        ];
        $('#bib-visor-ficha').innerHTML = ficha.map(([k, v]) => `<tr><td>${k}</td><td>${v}</td></tr>`).join('');
        $('#bib-visor-nivel').innerHTML = badgeNivel(p.nivel);

        let html = '';
        for (let i = 0; i < p.movimientos.length; i += 2) {
            html += `<div class="bib-jugada">
                <span class="bib-jugada-num">${i / 2 + 1}.</span>
                <button class="bib-jugada-btn" data-mov="${i}">${esc(p.movimientos[i])}</button>
                ${p.movimientos[i + 1] ? `<button class="bib-jugada-btn" data-mov="${i + 1}">${esc(p.movimientos[i + 1])}</button>` : ''}
            </div>`;
        }
        html += `<div class="bib-jugada-resultado">${esc(p.resultado)}</div>`;
        $('#bib-visor-jugadas').innerHTML = html;

        pintarVisor();
        mostrarVista('visor');
    }

    function pintarVisor() {
        const total = posiciones.length - 1;
        const pos = posiciones[movIndex + 1];

        let html = '';
        for (let r = 0; r < 8; r++) {
            for (let f = 0; f < 8; f++) {
                const clara = (r + f) % 2 === 0;
                const marcada = pos.desde && ((pos.desde.f === f && pos.desde.r === r) || (pos.hasta.f === f && pos.hasta.r === r));
                const p = pos.tab[r][f];
                html += `<div class="bib-casilla ${clara ? 'bib-clara' : 'bib-oscura'} ${marcada ? 'bib-marcada' : ''}">${p ? PIEZAS[p] : ''}</div>`;
            }
        }
        $('#bib-tablero').innerHTML = html;

        $('#bib-visor-pos').textContent = movIndex === -1 ? 'Inicio' : `Mov. ${movIndex + 1} / ${total}`;
        $('#bib-visor-inicio').disabled = movIndex === -1;
        $('#bib-visor-ant').disabled = movIndex === -1;
        $('#bib-visor-sig').disabled = movIndex === total - 1;
        $('#bib-visor-fin').disabled = movIndex === total - 1;

        $$('.bib-jugada-btn').forEach(b => {
            const i = Number(b.dataset.mov);
            b.classList.toggle('active', i === movIndex);
            b.disabled = i > total - 1;
        });
        seccion.querySelector(`.bib-jugada-btn[data-mov="${movIndex}"]`)?.scrollIntoView({ block: 'nearest' });
    }

    const irA = (i) => { movIndex = Math.max(-1, Math.min(posiciones.length - 2, i)); pintarVisor(); };
    $('#bib-visor-inicio').addEventListener('click', () => irA(-1));
    $('#bib-visor-ant').addEventListener('click', () => irA(movIndex - 1));
    $('#bib-visor-sig').addEventListener('click', () => irA(movIndex + 1));
    $('#bib-visor-fin').addEventListener('click', () => irA(posiciones.length - 2));
    $('#bib-visor-jugadas').addEventListener('click', (e) => {
        const b = e.target.closest('[data-mov]');
        if (b) irA(Number(b.dataset.mov));
    });

    // Flechas del teclado cuando el visor está abierto
    document.addEventListener('keydown', (e) => {
        if (seccion.style.display === 'none' || $('.bib-vista[data-vista="visor"]').style.display !== 'block') return;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
        if (e.key === 'ArrowRight') { e.preventDefault(); irA(movIndex + 1); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); irA(movIndex - 1); }
    });

    // =============================================
    //  GESTIÓN DE MATERIAL
    // =============================================
    function renderGestion() {
        const q = $('#bib-gestion-buscar').value.trim().toLowerCase();
        const filtrados = documentos.filter(d =>
            !q || d.titulo.toLowerCase().includes(q) || d.creador.toLowerCase().includes(q));
        $('#bib-gestion-tbody').innerHTML = filtrados.length
            ? filtrados.map(d => `
                <tr>
                    <td><p class="bib-negrita">${esc(d.titulo)}</p><p class="bib-texto-suave">${esc(d.creador)}</p></td>
                    <td>${badgeTipo(d.tipo)}</td>
                    <td>${badgeNivel(d.nivel)}</td>
                    <td><span class="bib-eco">${esc(d.formato)}</span></td>
                    <td class="bib-texto-suave">${esc(d.tamano)}</td>
                    <td class="bib-texto-suave">${esc(fechaCorta(d.fecha))}</td>
                    <td><button class="bib-estatus ${d.estatus === 'Activo' ? 'bib-estatus-activo' : 'bib-estatus-inactivo'}"
                        data-estatus="${d.id}" title="Cambiar estatus">${esc(d.estatus)}</button></td>
                    <td class="bib-derecha bib-acciones">
                        <button class="bib-icono-btn" data-ver-doc="${d.id}" title="Ver"><i class="ri-eye-line"></i></button>
                        <button class="bib-icono-btn" data-editar="${d.id}" title="Editar"><i class="ri-pencil-line"></i></button>
                        <button class="bib-icono-btn bib-icono-peligro" data-eliminar="${d.id}" title="Eliminar"><i class="ri-delete-bin-line"></i></button>
                    </td>
                </tr>`).join('')
            : `<tr><td colspan="8" class="bib-vacio-texto">No hay documentos.</td></tr>`;
    }

    $('#bib-gestion-buscar').addEventListener('input', renderGestion);

    $('#bib-gestion-tbody').addEventListener('click', (e) => {
        const est = e.target.closest('[data-estatus]');
        if (est) {
            const d = documentos.find(x => x.id === Number(est.dataset.estatus));
            d.estatus = d.estatus === 'Activo' ? 'Inactivo' : 'Activo';
            renderGestion();
            return;
        }
        const ed = e.target.closest('[data-editar]');
        if (ed) { abrirModal(documentos.find(x => x.id === Number(ed.dataset.editar))); return; }
        const el = e.target.closest('[data-eliminar]');
        if (el) {
            const d = documentos.find(x => x.id === Number(el.dataset.eliminar));
            if (!confirm(`¿Eliminar "${d.titulo}"?`)) return;
            documentos = documentos.filter(x => x.id !== d.id);
            renderGestion();
            alerta('Material eliminado');
        }
    });

    // ── Modal Agregar / Editar ─────────────────────
    const modal = document.getElementById('bib-modal');
    const form = document.getElementById('bib-form');
    const zona = document.getElementById('bib-zona-archivo');
    const inputArchivo = document.getElementById('bib-form-archivo');
    let editandoId = null;
    let archivo = null;   // { nombre, tamano, sha256 }

    function abrirModal(doc = null) {
        form.reset();
        editandoId = doc ? doc.id : null;
        archivo = null;
        document.getElementById('bib-modal-titulo').textContent = doc ? 'Editar material' : 'Agregar material';
        if (doc) {
            form.titulo.value = doc.titulo;
            form.creador.value = doc.creador;
            form.tema.value = doc.tema;
            form.descripcion.value = doc.descripcion;
            form.tipo.value = doc.tipo;
            form.nivel.value = doc.nivel;
            form.palabrasClave.value = doc.palabrasClave.join(', ');
        }
        actualizarFormulario();
        modal.classList.add('open');
        form.titulo.focus();
    }
    const cerrarModal = () => modal.classList.remove('open');

    $('#bib-btn-agregar').addEventListener('click', () => abrirModal());
    document.getElementById('bib-modal-cancelar').addEventListener('click', cerrarModal);
    document.getElementById('bib-modal-cerrar').addEventListener('click', cerrarModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) cerrarModal(); });

    function actualizarFormulario() {
        const esVideo = form.tipo.value === 'Video';
        document.getElementById('bib-bloque-archivo').style.display = esVideo ? 'none' : '';
        document.getElementById('bib-bloque-video').style.display = esVideo ? '' : 'none';
        document.getElementById('bib-bloque-sha').style.display = archivo && !esVideo ? '' : 'none';
        form.sha256.value = archivo ? archivo.sha256 : '';
        zona.innerHTML = archivo
            ? `<div class="bib-archivo-elegido"><i class="ri-file-text-line"></i><span>${esc(archivo.nombre)}</span>
                <button type="button" class="bib-icono-btn" id="bib-quitar-archivo" title="Quitar"><i class="ri-close-line"></i></button></div>`
            : `<i class="ri-upload-2-line bib-zona-icono"></i>
               <p>Arrastra tu archivo aquí o haz clic para seleccionar</p>
               <p class="bib-texto-suave">PDF, imagen o PGN</p>`;
        const listo = form.titulo.value.trim() && form.tipo.value && form.nivel.value;
        document.getElementById('bib-modal-guardar').disabled = !listo;
    }

    form.addEventListener('input', actualizarFormulario);
    form.addEventListener('change', actualizarFormulario);

    async function calcularSHA256(file) {
        if (!window.crypto?.subtle) return '(no disponible en este navegador)';
        const buf = await file.arrayBuffer();
        const hash = await crypto.subtle.digest('SHA-256', buf);
        return [...new Uint8Array(hash)].map(b => b.toString(16).padStart(2, '0')).join('');
    }

    function formatoTamano(bytes) {
        if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    async function tomarArchivo(file) {
        if (!file) return;
        archivo = { nombre: file.name, tamano: formatoTamano(file.size), sha256: 'Calculando…' };
        actualizarFormulario();
        archivo.sha256 = await calcularSHA256(file);
        actualizarFormulario();
    }

    zona.addEventListener('click', (e) => {
        if (e.target.closest('#bib-quitar-archivo')) {
            e.stopPropagation();
            archivo = null;
            inputArchivo.value = '';
            actualizarFormulario();
            return;
        }
        inputArchivo.click();
    });
    inputArchivo.addEventListener('change', () => tomarArchivo(inputArchivo.files[0]));
    zona.addEventListener('dragover', (e) => { e.preventDefault(); zona.classList.add('bib-arrastrando'); });
    zona.addEventListener('dragleave', () => zona.classList.remove('bib-arrastrando'));
    zona.addEventListener('drop', (e) => {
        e.preventDefault();
        zona.classList.remove('bib-arrastrando');
        tomarArchivo(e.dataTransfer.files[0]);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const datos = {
            titulo: form.titulo.value.trim(),
            creador: form.creador.value.trim() || 'Sin especificar',
            tema: form.tema.value.trim() || 'General',
            descripcion: form.descripcion.value.trim(),
            tipo: form.tipo.value,
            nivel: form.nivel.value,
            palabrasClave: form.palabrasClave.value.split(',').map(s => s.trim()).filter(Boolean),
        };
        if (!datos.titulo || !datos.tipo || !datos.nivel) return;

        if (editandoId) {
            const d = documentos.find(x => x.id === editandoId);
            Object.assign(d, datos);
            if (archivo) { d.tamano = archivo.tamano; d.formato = archivo.nombre.toLowerCase().endsWith('.pgn') ? 'PGN' : 'PDF'; }
            alerta('Material actualizado');
        } else {
            const esVideo = datos.tipo === 'Video';
            const nombre = archivo?.nombre.toLowerCase() || '';
            documentos.unshift({
                id: Date.now(),
                ...datos,
                fecha: new Date().toISOString().slice(0, 10),
                formato: esVideo ? 'MP4' : nombre.endsWith('.pgn') ? 'PGN' : /\.(png|jpe?g)$/.test(nombre) ? 'Imagen' : 'PDF',
                idioma: 'Español',
                tamano: esVideo ? 'Enlace externo' : (archivo?.tamano || '—'),
                estatus: 'Activo',
                portada: PORTADAS[documentos.length % PORTADAS.length],
            });
            alerta('Material agregado (solo en esta sesión, aún no se guarda en la base de datos)', 'warning');
        }
        cerrarModal();
        renderGestion();
    });

    // ── Inicio ─────────────────────────────────────
    renderCatalogo();
    mostrarVista('catalogo');
});
