// ==============================================
//  dashboardAdminBiblioteca.js — Biblioteca Virtual
//  Catálogo, Detalle, Partidas, Visor, Guardados,
//  Historial y Gestión de material (SCRUM-18)
//  EGAU Chess | AMAAC
//
//  Lee y guarda con la API /api/biblioteca (SCRUM-27).
//  Permisos (SCRUM-46): ver, editar y eliminar vienen en
//  window.PERMISOS_BIBLIOTECA. "Mis guardados" e "Historial"
//  del personal se recuerdan en este navegador.
// ==============================================

document.addEventListener('DOMContentLoaded', () => {

    const seccion = document.getElementById('section-biblioteca');
    if (!seccion) return;

    const PERMISOS = window.PERMISOS_BIBLIOTECA || { ver: true, edit: false, eliminar: false };
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const API = '/api/biblioteca';

    // Mismos nombres que academico.nivel (Dama se muestra como Reina)
    const NIVELES = [
        { nombre: 'Peón',    icono: '♟', puntos: 0,    clase: 'bib-nivel-peon' },
        { nombre: 'Caballo', icono: '♞', puntos: 150,  clase: 'bib-nivel-caballo' },
        { nombre: 'Alfil',   icono: '♝', puntos: 400,  clase: 'bib-nivel-alfil' },
        { nombre: 'Torre',   icono: '♜', puntos: 800,  clase: 'bib-nivel-torre' },
        { nombre: 'Reina',   icono: '♛', puntos: 1500, clase: 'bib-nivel-reina' },
        { nombre: 'Rey',     icono: '♚', puntos: 3000, clase: 'bib-nivel-rey' },
    ];
    const SIN_NIVEL = { nombre: 'Todos', icono: '♙', puntos: 0, clase: 'bib-nivel-peon' };
    const TIPOS = [
        { nombre: 'Libro',     icono: 'ri-book-open-line', clase: 'bib-tipo-libro' },
        { nombre: 'Partida',   icono: 'ri-sword-line',     clase: 'bib-tipo-partida' },
        { nombre: 'Apertura',  icono: 'ri-git-branch-line', clase: 'bib-tipo-partida' },
        { nombre: 'Video',     icono: 'ri-video-line',     clase: 'bib-tipo-video' },
        { nombre: 'Ejercicio', icono: 'ri-file-text-line', clase: 'bib-tipo-ejercicio' },
    ];

    const PAGE_SIZE = 12;
    const LS_GUARDADOS = 'egau_bib_guardados_v2';
    const LS_HISTORIAL = 'egau_bib_historial_v2';

    // ── API ─────────────────────────────────────────
    const controladores = {};
    async function api(ruta, { metodo = 'GET', datos = null, params = null, clave = null } = {}) {
        if (clave) { controladores[clave]?.abort(); controladores[clave] = new AbortController(); }
        const url = API + ruta + (params ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)) : '');
        const opciones = { method: metodo, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
            signal: clave ? controladores[clave].signal : undefined };
        if (datos instanceof FormData) opciones.body = datos;
        else if (datos) { opciones.body = JSON.stringify(datos); opciones.headers['Content-Type'] = 'application/json'; }
        const r = await fetch(url, opciones);
        const json = await r.json().catch(() => ({}));
        if (!r.ok) {
            const err = new Error(json.error || (json.errors ? Object.values(json.errors)[0][0] : `Error ${r.status}`));
            err.status = r.status; err.json = json;
            throw err;
        }
        return json;
    }
    const ignorarAbort = (e) => { if (e.name !== 'AbortError') { console.error(e); alerta(e.message, 'error'); } };
    const debounce = (fn, ms = 350) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

    // Guardados e historial del personal: se recuerdan en este navegador (el alumno los tiene en la base)
    const cache = new Map();   // id -> documento ya cargado
    const recordar = (docs) => docs.forEach(d => cache.set(d.id, d));

    const $ = (sel) => seccion.querySelector(sel);
    const $$ = (sel) => seccion.querySelectorAll(sel);

    // ── Utilidades ─────────────────────────────────
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);

    const nivelInfo = (n) => NIVELES.find(x => x.nombre === n) || SIN_NIVEL;
    const tipoInfo = (t) => TIPOS.find(x => x.nombre === t) || TIPOS[0];

    const fechaOk = (f) => f && !isNaN(new Date(String(f).slice(0, 10) + 'T00:00:00'));
    const fechaLarga = (f) => !fechaOk(f) ? '—' : new Date(String(f).slice(0, 10) + 'T00:00:00').toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
    const fechaCorta = (f) => !fechaOk(f) ? '—' : new Date(String(f).slice(0, 10) + 'T00:00:00').toLocaleDateString('es-MX');
    const fechaMedia = (f) => !fechaOk(f) ? '—' : new Date(String(f).slice(0, 10) + 'T00:00:00').toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });

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

    let guardados = leerLS(LS_GUARDADOS, []);    // [documento resumido]
    let historial = leerLS(LS_HISTORIAL, []);    // [{ doc, fechaConsulta }]
    const resumen = (d) => ({ id: d.id, titulo: d.titulo, creador: d.creador, nivel: d.nivel, tipo: d.tipo, formato: d.formato, portada: d.portada });

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
    api('/filtros').then(r => llenarSelect($('#bib-filtro-anio'), r.anios.map(a => ({ valor: a, texto: a })))).catch(ignorarAbort);
    llenarSelect($('#bib-filtro-nivel'), opcionesNivel);
    llenarSelect($('#bib-filtro-tipo'), opcionesTipo);
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

    function paginador(cont, meta) {
        const total = meta?.pages || 1, actual = meta?.page || 1;
        if (total <= 1) { cont.innerHTML = ''; return; }
        const nums = [];
        for (let p = 1; p <= total; p++) if (p === 1 || p === total || Math.abs(p - actual) <= 2) nums.push(p); else if (nums[nums.length - 1] !== '…') nums.push('…');
        cont.innerHTML = `<button class="bib-btn-outline bib-btn-sm" data-pag="${actual - 1}" ${actual === 1 ? 'disabled' : ''}>Anterior</button>`
            + nums.map(p => p === '…' ? '<span class="bib-texto-suave">…</span>'
                : `<button class="bib-btn-sm ${p === actual ? 'bib-btn-activo' : 'bib-btn-outline'}" data-pag="${p}">${p}</button>`).join('')
            + `<button class="bib-btn-outline bib-btn-sm" data-pag="${actual + 1}" ${actual === total ? 'disabled' : ''}>Siguiente</button>`;
    }

    // Catálogo: libros, videos, ejercicios y partidas publicados. Las aperturas ECO están en "Partidas".
    async function renderCatalogo() {
        const tipo = $('#bib-filtro-tipo').value;
        try {
            const r = await api('/recursos', { clave: 'catalogo', params: {
                q: $('#bib-buscar').value.trim(), nivel: $('#bib-filtro-nivel').value, anio: $('#bib-filtro-anio').value,
                tipo, sin_tipo: tipo ? '' : 'apertura', estatus: 'activo', page: pagina, per_page: PAGE_SIZE } });
            recordar(r.data);
            const n = r.meta.total;
            $('#bib-conteo').textContent = `${n} documento${n !== 1 ? 's' : ''} encontrado${n !== 1 ? 's' : ''}`;
            $('#bib-grid').innerHTML = r.data.length
                ? r.data.map(tarjetaDocumento).join('')
                : `<div class="bib-vacio-texto">No se encontraron documentos con los filtros seleccionados.</div>`;
            paginador($('#bib-paginacion'), r.meta);
        } catch (e) { ignorarAbort(e); }
    }

    $('#bib-paginacion').addEventListener('click', (e) => {
        const b = e.target.closest('[data-pag]');
        if (!b || b.disabled) return;
        pagina = Number(b.dataset.pag);
        renderCatalogo();
    });

    $('#bib-buscar').addEventListener('input', debounce(() => { pagina = 1; renderCatalogo(); }));
    ['#bib-filtro-nivel', '#bib-filtro-tipo', '#bib-filtro-anio'].forEach(id =>
        $(id).addEventListener('change', () => { pagina = 1; renderCatalogo(); })
    );

    // =============================================
    //  DETALLE DE DOCUMENTO
    // =============================================
    let docActual = null;

    async function abrirDocumento(id) {
        let doc;
        try { doc = await api(`/recursos/${id}`, { clave: 'documento' }); } catch (e) { return ignorarAbort(e); }
        if (doc.tipo === 'Partida' || doc.tipo === 'Apertura') return abrirVisor(id, doc);
        cache.set(doc.id, doc);

        const vistaActual = [...$$('.bib-vista')].find(v => v.style.display === 'block')?.dataset.vista;
        if (vistaActual && vistaActual !== 'documento') vistaAnterior = PESTANA_DE_VISTA[vistaActual] || vistaActual;
        $('#bib-doc-volver-texto').textContent = {
            catalogo: 'Volver al catálogo', guardados: 'Volver a mis guardados',
            historial: 'Volver al historial', gestion: 'Volver a gestión de material',
        }[vistaAnterior] || 'Volver';

        docActual = doc;
        registrarHistorial(doc);

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
            ['Nivel', doc.nivel ? `${ni.icono} ${ni.nombre} (${ni.puntos}+ pts)` : 'Todos los niveles'],
            ['Palabras clave', (doc.palabrasClave || []).join(', ') || '—'],
            ['Tamaño', doc.tamano || '—'],
            ['Estatus', doc.estatus],
        ];
        $('#bib-doc-meta').innerHTML = metadatos.map(([k, v]) =>
            `<tr><td>${esc(k)}</td><td>${esc(v)}</td></tr>`).join('');

        pintarBotonGuardar();

        // Relacionados: mismo nivel o tipo (los pide al servidor)
        const relacionados = (await api('/recursos', { params: { tipo: doc.tipo_clave, estatus: 'activo', per_page: 4 } }).catch(() => ({ data: [] })))
            .data.filter(d => d.id !== doc.id).slice(0, 3);
        recordar(relacionados);
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
        const esta = docActual && guardados.some(g => g.id === docActual.id);
        btn.classList.toggle('bib-btn-guardado', !!esta);
        btn.innerHTML = esta
            ? '<i class="ri-bookmark-fill"></i> Guardado'
            : '<i class="ri-bookmark-line"></i> Guardar';
    }

    $('#bib-doc-guardar').addEventListener('click', () => {
        if (!docActual) return;
        if (guardados.some(g => g.id === docActual.id)) {
            guardados = guardados.filter(g => g.id !== docActual.id);
            alerta('Documento quitado de tus guardados', 'warning');
        } else {
            guardados = [resumen(docActual), ...guardados].slice(0, 100);
            alerta('Documento guardado');
        }
        escribirLS(LS_GUARDADOS, guardados);
        pintarBotonGuardar();
    });

    // Ver / descargar con URL firmada que caduca (SCRUM-31)
    async function abrirArchivo(descargar) {
        if (!docActual) return;
        if (docActual.url && !docActual.tiene_archivo) { window.open(docActual.url, '_blank', 'noopener'); return; }
        if (!docActual.tiene_archivo) { alerta('Este material no tiene archivo', 'warning'); return; }
        const ventana = window.open('', '_blank');   // se abre antes del fetch para que el navegador no la bloquee
        try {
            const r = await api(`/recursos/${docActual.id}/enlace`);
            if (ventana) { ventana.opener = null; ventana.location = descargar ? r.descargar : r.ver; }
        } catch (e) { ventana?.close(); ignorarAbort(e); }
    }
    $('#bib-doc-ver').addEventListener('click', () => abrirArchivo(false));
    $('#bib-doc-descargar').addEventListener('click', () => abrirArchivo(true));

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
        const docs = guardados;
        $('#bib-guardados-vacio').style.display = docs.length ? 'none' : 'block';
        $('#bib-guardados-lista').style.display = docs.length ? 'block' : 'none';
        $('#bib-guardados-lista').innerHTML = docs.map(doc =>
            filaLista(doc, `<span class="bib-texto-suave">${esc(doc.formato)}</span>`, `data-quitar-guardado="${doc.id}"`)
        ).join('');
    }

    $('#bib-guardados-lista').addEventListener('click', (e) => {
        const b = e.target.closest('[data-quitar-guardado]');
        if (!b) return;
        guardados = guardados.filter(g => g.id !== Number(b.dataset.quitarGuardado));
        escribirLS(LS_GUARDADOS, guardados);
        renderGuardados();
    });

    function registrarHistorial(doc) {
        const hoy = new Date();
        const f = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
        historial = [{ doc: resumen(doc), fechaConsulta: f }, ...historial.filter(h => h.doc?.id !== doc.id)].slice(0, 50);
        escribirLS(LS_HISTORIAL, historial);
    }

    function renderHistorial() {
        const items = historial
            .map(h => ({ doc: h.doc, fecha: h.fechaConsulta }))
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
        historial = historial.filter(h => h.doc?.id !== Number(b.dataset.quitarHistorial));
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
    const RESULTADO_CLASE = { '1-0': 'bib-res-blancas', '0-1': 'bib-res-negras', '½-½': 'bib-res-tablas', '1/2-1/2': 'bib-res-tablas' };

    let paginaPartidas = 1;
    async function renderPartidas() {
        const tipo = $('#bib-partidas-tipo')?.value || 'partida';
        try {
            const r = await api('/recursos', { clave: 'partidas', params: {
                q: $('#bib-partidas-buscar').value.trim(), nivel: $('#bib-partidas-nivel').value,
                tipo, estatus: 'activo', page: paginaPartidas, per_page: 25 } });
            recordar(r.data);
            const conteo = $('#bib-partidas-conteo');
            if (conteo) conteo.textContent = `${r.meta.total} ${tipo === 'apertura' ? 'apertura' : 'partida'}${r.meta.total !== 1 ? 's' : ''}`;
            $('#bib-partidas-tbody').innerHTML = r.data.length
                ? r.data.map(p => `
                <tr>
                    <td class="bib-negrita">${esc(p.blancas || (p.tipo === 'Apertura' ? p.titulo : '—'))}</td>
                    <td>${esc(p.negras || '—')}</td>
                    <td><span class="bib-resultado ${RESULTADO_CLASE[p.resultado] || ''}">${esc(p.resultado || '*')}</span></td>
                    <td>${esc(p.apertura || p.titulo)} ${p.eco ? `<span class="bib-eco">${esc(p.eco)}</span>` : ''}</td>
                    <td>${badgeNivel(p.nivel)}</td>
                    <td class="bib-texto-suave">${esc(fechaCorta(p.fecha))}</td>
                    <td class="bib-derecha">
                        <button class="bib-btn-outline bib-btn-sm" data-ver-partida="${p.id}"><i class="ri-eye-line"></i> Ver</button>
                    </td>
                </tr>`).join('')
                : `<tr><td colspan="7" class="bib-vacio-texto">No se encontraron partidas.</td></tr>`;
            const pag = $('#bib-partidas-paginacion');
            if (pag) paginador(pag, r.meta);
        } catch (e) { ignorarAbort(e); }
    }

    $('#bib-partidas-buscar').addEventListener('input', debounce(() => { paginaPartidas = 1; renderPartidas(); }));
    $('#bib-partidas-nivel').addEventListener('change', () => { paginaPartidas = 1; renderPartidas(); });
    $('#bib-partidas-tipo')?.addEventListener('change', () => { paginaPartidas = 1; renderPartidas(); });
    $('#bib-partidas-paginacion')?.addEventListener('click', (e) => {
        const b = e.target.closest('[data-pag]');
        if (!b || b.disabled) return;
        paginaPartidas = Number(b.dataset.pag);
        renderPartidas();
    });

    // =============================================
    //  VISOR DE PARTIDA (comun/visorPartida.js, SCRUM-37):
    //  voltear, teclado, variantes y exportar PGN
    // =============================================
    let partidaActual = null;
    const visor = window.EgauVisor ? window.EgauVisor.crear($('#bib-visor-ev')) : null;

    async function abrirVisor(id, yaCargada = null) {
        let p = yaCargada && yaCargada.fens ? yaCargada : null;
        if (!p) {
            try { p = await api(`/recursos/${id}`, { clave: 'visor' }); } catch (e) { return ignorarAbort(e); }
        }
        partidaActual = p;
        cache.set(p.id, p);
        registrarHistorial(p);

        $('#bib-visor-titulo').textContent = p.blancas ? `${p.blancas} vs ${p.negras || '?'}` : p.titulo;
        const ficha = [
            ['Blancas', esc(p.blancas || '—')],
            ['Negras', esc(p.negras || '—')],
            ['Resultado', `<span class="bib-resultado ${RESULTADO_CLASE[p.resultado] || ''}">${esc(p.resultado || '*')}</span>`],
            ['Fecha', esc(fechaCorta(p.fecha))],
            ['Evento', esc(p.evento || '—')],
            ['Apertura', esc(p.apertura || '—')],
            ['ECO', p.eco ? `<span class="bib-eco">${esc(p.eco)}</span>` : '—'],
        ];
        $('#bib-visor-ficha').innerHTML = ficha.map(([k, v]) => `<tr><td>${k}</td><td>${v}</td></tr>`).join('');
        $('#bib-visor-nivel').innerHTML = badgeNivel(p.nivel);

        visor?.cargar(p, 0);
        mostrarVista('visor');
    }

    // =============================================
    //  GESTIÓN DE MATERIAL
    // =============================================
    let paginaGestion = 1;
    async function renderGestion() {
        try {
            const r = await api('/recursos', { clave: 'gestion', params: {
                q: $('#bib-gestion-buscar').value.trim(), sin_tipo: 'apertura', page: paginaGestion, per_page: 25 } });
            recordar(r.data);
            $('#bib-gestion-tbody').innerHTML = r.data.length
                ? r.data.map(d => `
                <tr>
                    <td><p class="bib-negrita">${esc(d.titulo)}</p><p class="bib-texto-suave">${esc(d.creador)}</p></td>
                    <td>${badgeTipo(d.tipo)}</td>
                    <td>${badgeNivel(d.nivel)}</td>
                    <td><span class="bib-eco">${esc(d.formato || '—')}</span></td>
                    <td class="bib-texto-suave">${esc(d.tamano || '—')}</td>
                    <td class="bib-texto-suave">${esc(fechaCorta(d.fecha))}</td>
                    <td><button class="bib-estatus ${d.estatus === 'Activo' ? 'bib-estatus-activo' : 'bib-estatus-inactivo'}"
                        data-estatus="${d.id}" title="${PERMISOS.edit ? 'Cambiar estatus' : ''}" ${PERMISOS.edit ? '' : 'disabled'}>${esc(d.estatus)}</button></td>
                    <td class="bib-derecha bib-acciones">
                        <button class="bib-icono-btn" data-ver-doc="${d.id}" title="Ver"><i class="ri-eye-line"></i></button>
                        ${PERMISOS.edit ? `<button class="bib-icono-btn" data-editar="${d.id}" title="Editar"><i class="ri-pencil-line"></i></button>` : ''}
                        ${PERMISOS.eliminar ? `<button class="bib-icono-btn bib-icono-peligro" data-eliminar="${d.id}" title="Eliminar"><i class="ri-delete-bin-line"></i></button>` : ''}
                    </td>
                </tr>`).join('')
                : `<tr><td colspan="8" class="bib-vacio-texto">No hay documentos.</td></tr>`;
            const pag = $('#bib-gestion-paginacion');
            if (pag) paginador(pag, r.meta);
        } catch (e) { ignorarAbort(e); }
    }

    $('#bib-gestion-buscar').addEventListener('input', debounce(() => { paginaGestion = 1; renderGestion(); }));
    $('#bib-gestion-paginacion')?.addEventListener('click', (e) => {
        const b = e.target.closest('[data-pag]');
        if (!b || b.disabled) return;
        paginaGestion = Number(b.dataset.pag);
        renderGestion();
    });

    $('#bib-gestion-tbody').addEventListener('click', async (e) => {
        try {
            const est = e.target.closest('[data-estatus]');
            if (est && PERMISOS.edit) {
                const d = cache.get(Number(est.dataset.estatus));
                const r = await api(`/recursos/${d.id}/estatus`, { metodo: 'PATCH', datos: { publicado: d.estatus !== 'Activo' } });
                alerta(r.message);
                renderGestion();
                return;
            }
            const ed = e.target.closest('[data-editar]');
            if (ed) { abrirModal(await api(`/recursos/${ed.dataset.editar}`)); return; }
            const el = e.target.closest('[data-eliminar]');
            if (el) {
                const d = cache.get(Number(el.dataset.eliminar));
                if (!confirm(`¿Eliminar "${d.titulo}"? También se borra el progreso de los alumnos en este material.`)) return;
                const r = await api(`/recursos/${d.id}`, { metodo: 'DELETE' });
                guardados = guardados.filter(g => g.id !== d.id); escribirLS(LS_GUARDADOS, guardados);
                renderGestion();
                alerta(r.message);
            }
        } catch (err) { ignorarAbort(err); }
    });

    // ── Modal Agregar / Editar ─────────────────────
    const modal = document.getElementById('bib-modal');
    const form = document.getElementById('bib-form');
    const zona = document.getElementById('bib-zona-archivo');
    const inputArchivo = document.getElementById('bib-form-archivo');
    let editandoId = null;
    let archivo = null;   // { nombre, tamano, sha256 }

    const CON_PGN = ['Partida', 'Apertura'];
    const ACEPTA = { pdf: 'PDF', epub: 'EPUB', mp4: 'MP4', webm: 'WEBM' };
    let archivoFile = null;

    function abrirModal(doc = null) {
        form.reset();
        editandoId = doc ? doc.id : null;
        archivo = null; archivoFile = null;
        document.getElementById('bib-modal-titulo').textContent = doc ? 'Editar material' : 'Agregar material';
        document.getElementById('bib-pgn-estado') && (document.getElementById('bib-pgn-estado').textContent = '');
        if (doc) {
            form.titulo.value = doc.titulo;
            form.creador.value = doc.creador || '';
            form.tema.value = doc.tema || '';
            form.descripcion.value = doc.descripcion || '';
            form.tipo.value = doc.tipo;
            form.nivel.value = doc.nivel || '';
            form.palabrasClave.value = (doc.palabrasClave || []).join(', ');
            if (form.enlaceVideo) form.enlaceVideo.value = doc.url || '';
            if (form.pgn) form.pgn.value = doc.pgn || '';
            if (doc.sha256) archivo = { nombre: 'Archivo actual', tamano: doc.tamano, sha256: doc.sha256, actual: true };
        }
        actualizarFormulario();
        modal.classList.add('open');
        form.titulo.focus();
    }
    const cerrarModal = () => modal.classList.remove('open');

    if (!PERMISOS.edit && !PERMISOS.eliminar) seccion.querySelector('.bib-tab[data-vista="gestion"]')?.remove();
    const btnAgregar = $('#bib-btn-agregar');
    if (!PERMISOS.edit && btnAgregar) btnAgregar.style.display = 'none';
    btnAgregar?.addEventListener('click', () => abrirModal());
    document.getElementById('bib-modal-cancelar').addEventListener('click', cerrarModal);
    document.getElementById('bib-modal-cerrar').addEventListener('click', cerrarModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) cerrarModal(); });

    function actualizarFormulario() {
        const tipo = form.tipo.value;
        const esVideo = tipo === 'Video';
        const conPgn = CON_PGN.includes(tipo);
        document.getElementById('bib-bloque-archivo').style.display = conPgn ? 'none' : '';
        document.getElementById('bib-bloque-video').style.display = esVideo ? '' : 'none';
        const bloquePgn = document.getElementById('bib-bloque-pgn');
        if (bloquePgn) bloquePgn.style.display = conPgn || tipo === 'Ejercicio' ? '' : 'none';
        document.getElementById('bib-bloque-sha').style.display = archivo && !conPgn ? '' : 'none';
        form.sha256.value = archivo ? archivo.sha256 : '';
        zona.innerHTML = archivo
            ? `<div class="bib-archivo-elegido"><i class="ri-file-text-line"></i><span>${esc(archivo.nombre)} ${archivo.tamano ? '· ' + esc(archivo.tamano) : ''}</span>
                <button type="button" class="bib-icono-btn" id="bib-quitar-archivo" title="Quitar"><i class="ri-close-line"></i></button></div>`
            : `<i class="ri-upload-2-line bib-zona-icono"></i>
               <p>Arrastra tu archivo aquí o haz clic para seleccionar</p>
               <p class="bib-texto-suave">PDF o EPUB hasta 50 MB · MP4 o WEBM hasta 200 MB · PGN</p>`;
        const pgnOk = !conPgn || (form.pgn && form.pgn.value.trim());
        const listo = form.titulo.value.trim() && tipo && pgnOk;
        document.getElementById('bib-modal-guardar').disabled = !listo;
    }

    form.addEventListener('input', actualizarFormulario);
    form.addEventListener('change', actualizarFormulario);

    // Revisa el PGN en el servidor mientras se escribe (SCRUM-30)
    const revisarPgn = debounce(async () => {
        const estado = document.getElementById('bib-pgn-estado');
        if (!form.pgn || !estado) return;
        const pgn = form.pgn.value.trim();
        if (!pgn) { estado.textContent = ''; return; }
        try {
            const r = await api('/validar-pgn', { metodo: 'POST', datos: { pgn }, clave: 'pgn' });
            estado.className = 'bib-texto-suave';
            estado.textContent = `✓ ${r.jugadas} jugadas válidas · resultado ${r.resultado}`;
            if (!form.creador.value && r.etiquetas.White) form.creador.value = `${r.etiquetas.White} vs ${r.etiquetas.Black || '?'}`;
        } catch (e) {
            if (e.name === 'AbortError') return;
            estado.className = 'bib-error';
            estado.textContent = e.json?.error || e.message;
        }
    }, 500);
    form.pgn?.addEventListener('input', revisarPgn);

    async function calcularSHA256(file) {
        if (!window.crypto?.subtle) return '(se calcula al guardar)';
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
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext === 'pgn') {   // un .pgn se pasa al cuadro de texto y se valida
            if (!CON_PGN.includes(form.tipo.value)) form.tipo.value = 'Partida';
            form.pgn.value = await file.text();
            actualizarFormulario();
            revisarPgn();
            return;
        }
        if (!ACEPTA[ext]) { alerta('Tipo de archivo no permitido. Usa PDF, EPUB, MP4, WEBM o PGN.', 'error'); return; }
        archivoFile = file;
        archivo = { nombre: file.name, tamano: formatoTamano(file.size), sha256: 'Calculando…' };
        actualizarFormulario();
        archivo.sha256 = await calcularSHA256(file);
        actualizarFormulario();
    }

    zona.addEventListener('click', (e) => {
        if (e.target.closest('#bib-quitar-archivo')) {
            e.stopPropagation();
            archivo = null; archivoFile = null;
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

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData();
        fd.append('titulo', form.titulo.value.trim());
        fd.append('tipo', form.tipo.value);
        fd.append('creador', form.creador.value.trim());
        fd.append('tema', form.tema.value.trim());
        fd.append('descripcion', form.descripcion.value.trim());
        fd.append('nivel', form.nivel.value);
        fd.append('palabras_clave', form.palabrasClave.value);
        if (form.tipo.value === 'Video' && form.enlaceVideo?.value.trim()) fd.append('url', form.enlaceVideo.value.trim());
        if (form.pgn && form.pgn.value.trim() && (CON_PGN.includes(form.tipo.value) || form.tipo.value === 'Ejercicio')) fd.append('pgn', form.pgn.value);
        if (archivoFile) fd.append('archivo', archivoFile);

        const btn = document.getElementById('bib-modal-guardar');
        btn.disabled = true;
        btn.textContent = archivoFile ? 'Subiendo…' : 'Guardando…';
        try {
            const r = await api(editandoId ? `/recursos/${editandoId}` : '/recursos', { metodo: 'POST', datos: fd });
            alerta(r.message);
            cerrarModal();
            renderGestion();
        } catch (err) {
            alerta(err.message, 'error');
        } finally {
            btn.textContent = 'Guardar material';
            actualizarFormulario();
        }
    });

    // ── Inicio ─────────────────────────────────────
    renderCatalogo();
    mostrarVista('catalogo');
});
