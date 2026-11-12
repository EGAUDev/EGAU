// ==============================================
//  dashboardAlumnoBiblioteca.js — Biblioteca del alumno
//  SCRUM-35  Sección con la API real (/api/biblioteca/alumno)
//  SCRUM-36  Mi progreso: vistos, avance por nivel y "continuar"
//  SCRUM-37  Visor de partidas (comun/visorPartida.js)
//  SCRUM-38  Lector de PDF que recuerda la página
//  SCRUM-39  Recomendaciones según el nivel
//  EGAU Chess | Portal del Estudiante
// ==============================================

document.addEventListener('DOMContentLoaded', () => {

    const seccion = document.getElementById('section-biblioteca');
    if (!seccion) return;

    const API = '/api/biblioteca/alumno';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const TOKEN = document.querySelector('meta[name="user-token"]')?.content || '';
    const POR_PAGINA = 12;
    const PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';

    const NIVELES = [
        { nombre: 'Peón', icono: '♟' }, { nombre: 'Caballo', icono: '♞' }, { nombre: 'Alfil', icono: '♝' },
        { nombre: 'Torre', icono: '♜' }, { nombre: 'Reina', icono: '♛' }, { nombre: 'Rey', icono: '♚' },
    ];
    const TIPO_ICONO = { libro: 'ri-book-open-line', partida: 'ri-sword-line', apertura: 'ri-git-branch-line', video: 'ri-video-line', problema: 'ri-puzzle-line' };

    const $ = (s) => seccion.querySelector(s);
    const $$ = (s) => seccion.querySelectorAll(s);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const alerta = (m, t = 'success') => window.egauAlert ? window.egauAlert(m, t) : console.log(m);
    const debounce = (fn, ms = 350) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
    const nivelIcono = (n) => NIVELES.find(x => x.nombre === n)?.icono || '♙';
    const portada = (d) => String(d.portada || 'bib-portada-1').replace('bib-', 'ab-');

    // ── API ─────────────────────────────────────────
    const controladores = {};
    async function api(ruta, { metodo = 'GET', datos = null, params = null, clave = null } = {}) {
        if (clave) { controladores[clave]?.abort(); controladores[clave] = new AbortController(); }
        const qs = params ? '?' + new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)) : '';
        const op = {
            method: metodo, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': CSRF, ...(TOKEN ? { Authorization: 'Bearer ' + TOKEN } : {}) },
            signal: clave ? controladores[clave].signal : undefined,
        };
        if (datos) { op.body = JSON.stringify(datos); op.headers['Content-Type'] = 'application/json'; }
        const r = await fetch(API + ruta + qs, op);
        const json = await r.json().catch(() => ({}));
        if (!r.ok) { const e = new Error(json.error || `Error ${r.status}`); e.status = r.status; throw e; }
        return json;
    }
    const fallo = (e) => { if (e.name !== 'AbortError') { console.error(e); alerta(e.message, 'error'); } };

    // ── Tarjetas y listas ───────────────────────────
    function tarjeta(d, { motivo = null, porcentaje = null } = {}) {
        const sub = d.tipo_clave === 'apertura' ? `${d.eco || ''} · Apertura` : (d.creador || d.tema || d.tipo);
        return `
        <button type="button" class="ab-card" data-abrir="${d.id}">
            <div class="ab-card-portada ${esc(portada(d))}">
                <span class="ab-card-marca">${nivelIcono(d.nivel)}</span>
                <i class="${TIPO_ICONO[d.tipo_clave] || 'ri-file-line'}"></i>
                <span class="ab-card-tipo">${esc(d.tipo)}</span>
            </div>
            <div class="ab-card-cuerpo">
                <div class="ab-card-titulo">${esc(d.titulo)}</div>
                <div class="ab-card-sub">${esc(sub)}</div>
                ${motivo ? `<div class="ab-card-motivo">${esc(motivo)}</div>` : ''}
                ${porcentaje != null ? `<div class="ab-progreso-linea"><div class="ab-progreso-relleno" style="width:${Math.round(porcentaje)}%"></div></div>` : ''}
            </div>
        </button>`;
    }
    const vacio = (icono, texto) => `<div class="ab-vacio"><i class="${icono}"></i>${texto}</div>`;

    function item(d) {
        const p = Math.round(d.porcentaje || 0);
        const f = d.ultima_consulta ? new Date(String(d.ultima_consulta).slice(0, 10) + 'T12:00:00') : null;
        const cuando = f && !isNaN(f) ? f.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) : '';
        return `
        <button type="button" class="ab-item" data-abrir="${d.id}">
            <span class="ab-item-icono ${esc(portada(d))}"><i class="${TIPO_ICONO[d.tipo_clave] || 'ri-file-line'}"></i></span>
            <span class="ab-item-texto">
                <span class="ab-item-titulo">${d.favorito ? '<i class="ri-heart-3-fill" style="color:#e0245e"></i> ' : ''}${esc(d.titulo)}</span>
                <span class="ab-item-sub">${esc(d.tipo)}${cuando ? ' · ' + cuando : ''}</span>
            </span>
            <span class="ab-item-porcentaje ${p >= 100 ? 'listo' : ''}">${p >= 100 ? '<i class="ri-check-line"></i>' : p + '%'}</span>
        </button>`;
    }

    // ── Pestañas ────────────────────────────────────
    let pestana = 'inicio';
    let pestanaAntes = 'inicio';
    function mostrar(vista) {
        $$('.ab-vista').forEach(v => { v.hidden = v.dataset.abVista !== vista; });
        if (vista !== 'recurso') {
            pestana = vista;
            $$('.ab-pestana').forEach(b => b.classList.toggle('active', b.dataset.abTab === vista));
            cerrarRecurso();
        }
        $('.ab-pestanas').hidden = vista === 'recurso';
    }
    const CARGAS = { inicio: () => cargarInicio(), explorar: () => buscar(), progreso: () => cargarProgreso(), favoritos: () => cargarFavoritos() };
    $$('.ab-pestana').forEach(b => b.addEventListener('click', () => { mostrar(b.dataset.abTab); CARGAS[b.dataset.abTab](); }));

    seccion.addEventListener('click', (e) => {
        const b = e.target.closest('[data-abrir]');
        if (b) { e.preventDefault(); abrir(Number(b.dataset.abrir)); }
    });

    // ── Para ti: continuar + recomendados (SCRUM-39) ──
    let miProgreso = null;
    async function cargarInicio() {
        $('#ab-recomendados').innerHTML = vacio('ri-loader-4-line', 'Cargando...');
        try {
            const [rec, prog] = await Promise.all([api('/recomendados', { clave: 'rec' }), api('/progreso', { clave: 'prog' })]);
            miProgreso = prog;
            const n = rec.nivel;
            $('#ab-recomendados-titulo').textContent = n ? `Recomendado para tu nivel ${n.emoji || ''} ${n.nombre}` : 'Recomendado para ti';
            $('#ab-recomendados').innerHTML = rec.data.length
                ? rec.data.map(d => tarjeta(d, { motivo: d.motivo })).join('')
                : vacio('ri-checkbox-circle-line', 'Ya abriste todo lo recomendado. Busca más en "Explorar".');
            pintarContinuar(prog.resumen.continuar);
        } catch (e) { fallo(e); }
    }

    function pintarContinuar(d) {
        const cont = $('#ab-continuar');
        if (!d) { cont.innerHTML = ''; return; }
        const m = d.marcador || {};
        const donde = m.pagina ? `Página ${m.pagina}` : m.jugada ? `Jugada ${m.jugada}` : m.segundo ? `Minuto ${Math.floor(m.segundo / 60)}:${String(Math.floor(m.segundo % 60)).padStart(2, '0')}` : '';
        cont.innerHTML = `
            <div class="ab-continuar">
                <div class="ab-continuar-icono ${esc(portada(d))}"><i class="${TIPO_ICONO[d.tipo_clave] || 'ri-file-line'}"></i></div>
                <div class="ab-continuar-texto">
                    <div class="ab-continuar-etiqueta">Continúa donde lo dejaste${donde ? ' · ' + esc(donde) : ''}</div>
                    <div class="ab-continuar-titulo">${esc(d.titulo)}</div>
                    <div class="ab-progreso-linea"><div class="ab-progreso-relleno" style="width:${Math.round(d.porcentaje || 0)}%"></div></div>
                </div>
                <button type="button" class="ab-btn" data-abrir="${d.id}"><i class="ri-play-fill"></i> Continuar</button>
            </div>`;
    }

    // ── Explorar ────────────────────────────────────
    const selNivel = $('#ab-filtro-nivel');
    selNivel.innerHTML += NIVELES.map(n => `<option value="${n.nombre}">${n.icono} ${n.nombre}</option>`).join('');
    let pagina = 1;

    async function buscar() {
        const q = $('#ab-buscar').value.trim();
        const tipo = $('#ab-filtro-tipo').value;
        const params = { q, tipo, nivel: selNivel.value, page: pagina, per_page: POR_PAGINA };
        if (!tipo && !q) params.sin_tipo = 'apertura';   // las aperturas son miles: solo si se buscan
        try {
            const r = await api('/recursos', { params, clave: 'buscar' });
            $('#ab-conteo').textContent = r.meta.total === 1 ? '1 resultado' : `${r.meta.total.toLocaleString('es-MX')} resultados`;
            $('#ab-resultados').innerHTML = r.data.length ? r.data.map(d => tarjeta(d)).join('') : vacio('ri-search-line', 'No encontramos material con esa búsqueda.');
            paginador(r.meta);
        } catch (e) { fallo(e); }
    }
    function paginador(meta) {
        const cont = $('#ab-paginacion');
        const total = meta.pages || 1, actual = meta.page || 1;
        if (total <= 1) { cont.innerHTML = ''; return; }
        const nums = [];
        for (let p = 1; p <= total; p++) {
            if (p === 1 || p === total || Math.abs(p - actual) <= 1) nums.push(p);
            else if (nums[nums.length - 1] !== '…') nums.push('…');
        }
        cont.innerHTML = `<button class="ab-btn-sec" data-pag="${actual - 1}" ${actual === 1 ? 'disabled' : ''} aria-label="Anterior"><i class="ri-arrow-left-s-line"></i></button>`
            + nums.map(p => p === '…' ? '<span>…</span>' : `<button class="ab-btn-sec ${p === actual ? 'actual' : ''}" data-pag="${p}">${p}</button>`).join('')
            + `<button class="ab-btn-sec" data-pag="${actual + 1}" ${actual === total ? 'disabled' : ''} aria-label="Siguiente"><i class="ri-arrow-right-s-line"></i></button>`;
    }
    $('#ab-paginacion').addEventListener('click', (e) => {
        const b = e.target.closest('[data-pag]');
        if (!b || b.disabled) return;
        pagina = Number(b.dataset.pag);
        buscar();
        $('.ab-filtros').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    $('#ab-buscar').addEventListener('input', debounce(() => { pagina = 1; buscar(); }));
    $('#ab-filtro-tipo').addEventListener('change', () => { pagina = 1; buscar(); });
    selNivel.addEventListener('change', () => { pagina = 1; buscar(); });

    // ── Mi progreso (SCRUM-36) ──────────────────────
    async function cargarProgreso() {
        try {
            miProgreso = await api('/progreso', { clave: 'prog' });
            const r = miProgreso.resumen;
            const kpis = [
                ['ri-eye-line', r.abiertos, 'Recursos vistos'],
                ['ri-check-double-line', r.terminados, 'Terminados'],
                ['ri-heart-3-line', r.favoritos, 'Favoritos'],
            ];
            $('#ab-kpis').innerHTML = kpis.map(([i, v, t]) => `<div class="ab-kpi"><div class="ab-kpi-valor"><i class="${i}" style="color:var(--naranja);font-size:20px"></i> ${v}</div><div class="ab-kpi-etiqueta">${t}</div></div>`).join('');

            $('#ab-por-nivel').innerHTML = (r.por_nivel || []).length ? r.por_nivel.map(n => {
                const t = n.total || 1, term = Math.min(100, n.terminados / t * 100), vis = Math.min(100 - term, (n.vistos - n.terminados) / t * 100);
                return `<div class="ab-nivel-fila">
                    <div class="ab-nivel-cab"><span>${n.emoji || '♙'} ${esc(n.nivel)}</span><span>${n.vistos} de ${n.total} vistos · ${n.terminados} terminados</span></div>
                    <div class="ab-barra"><div class="ab-barra-terminado" style="width:${term}%"></div><div class="ab-barra-visto" style="width:${Math.max(0, vis)}%"></div></div>
                </div>`;
            }).join('') + '<div class="ab-leyenda"><span><i class="ab-barra-terminado"></i>Terminado</span><span><i class="ab-barra-visto"></i>Visto</span></div>'
                : vacio('ri-bar-chart-2-line', 'Todavía no hay material para tu nivel.');

            const vistos = miProgreso.data.filter(d => d.veces_abierto > 0);
            $('#ab-historial').innerHTML = vistos.length ? vistos.map(item).join('') : vacio('ri-history-line', 'Aquí verás lo que vayas abriendo.');
        } catch (e) { fallo(e); }
    }

    async function cargarFavoritos() {
        try {
            miProgreso = await api('/progreso', { clave: 'prog' });
            const favs = miProgreso.data.filter(d => d.favorito);
            $('#ab-favoritos').innerHTML = favs.length ? favs.map(d => tarjeta(d, { porcentaje: d.porcentaje })).join('')
                : vacio('ri-heart-3-line', 'Marca con el corazón el material que quieras tener a la mano.');
        } catch (e) { fallo(e); }
    }

    // ── Recurso abierto ─────────────────────────────
    let actual = null;        // detalle del recurso abierto
    let porcentaje = 0;
    let visor = null;
    let limpiar = [];         // funciones para cerrar el lector/visor/video

    const guardarAvance = debounce((marcador) => {
        if (!actual) return;
        api(`/recursos/${actual.id}/progreso`, { metodo: 'POST', datos: { evento: 'avance', ...marcador } })
            .then(p => pintarPorcentaje(p.marcador?.porcentaje)).catch(fallo);
    }, 800);

    function pintarPorcentaje(p) {
        porcentaje = Math.max(porcentaje, Number(p) || 0);
        $('#ab-progreso-barra').style.width = Math.round(porcentaje) + '%';
        const b = $('#ab-terminado');
        b.disabled = porcentaje >= 100;
        b.innerHTML = porcentaje >= 100 ? '<i class="ri-check-double-line"></i> Terminado' : '<i class="ri-check-double-line"></i> Marcar como terminado';
    }
    function pintarFavorito(f) {
        const b = $('#ab-favorito');
        b.classList.toggle('activo', !!f);
        b.innerHTML = f ? '<i class="ri-heart-3-fill"></i>' : '<i class="ri-heart-3-line"></i>';
        b.title = f ? 'Quitar de favoritos' : 'Agregar a favoritos';
        b.dataset.fav = f ? '1' : '';
    }

    function cerrarRecurso() {
        limpiar.forEach(fn => { try { fn(); } catch { /* nada */ } });
        limpiar = [];
        actual = null;
    }

    async function abrir(id) {
        let d;
        try { d = await api(`/recursos/${id}`, { clave: 'detalle' }); } catch (e) { return fallo(e); }
        if (!document.querySelector('[data-ab-vista="recurso"]').hidden) cerrarRecurso();
        else pestanaAntes = pestana;
        cerrarRecurso();
        actual = d;
        porcentaje = 0;
        mostrar('recurso');
        window.scrollTo({ top: 0 });

        const chips = [`<span class="ab-chip">${esc(d.tipo)}</span>`];
        if (d.nivel) chips.push(`<span class="ab-chip">${nivelIcono(d.nivel)} ${esc(d.nivel)}</span>`);
        if (d.eco) chips.push(`<span class="ab-chip gris">${esc(d.eco)}</span>`);
        if (d.formato) chips.push(`<span class="ab-chip gris">${esc(d.formato)}</span>`);
        $('#ab-recurso-chips').innerHTML = chips.join('');
        $('#ab-recurso-titulo').textContent = d.blancas ? `${d.blancas} vs ${d.negras || '?'}` : d.titulo;
        $('#ab-recurso-sub').textContent = [d.blancas ? d.titulo : d.creador, d.evento, d.resultado && d.resultado !== '*' ? d.resultado : null].filter(Boolean).join(' · ');
        $('#ab-recurso-desc').textContent = d.tipo_clave === 'apertura' ? '' : (d.descripcion || '');
        pintarFavorito(d.progreso?.favorito);
        pintarPorcentaje(d.progreso?.marcador?.porcentaje || 0);
        $('#ab-descargar').hidden = !d.tiene_archivo;

        api(`/recursos/${id}/progreso`, { metodo: 'POST', datos: { evento: 'abrir' } }).catch(fallo);

        const m = d.progreso?.marcador || {};
        const cuerpo = $('#ab-recurso-cuerpo');
        if (d.fens && d.fens.length > 1) {
            abrirPartida(cuerpo, d, m);
        } else if (d.tiene_archivo && d.formato === 'PDF') {
            abrirPdf(cuerpo, d, m);
        } else if (d.tiene_archivo && ['MP4', 'WEBM'].includes(d.formato)) {
            abrirVideo(cuerpo, d, m);
        } else if (d.tiene_archivo) {
            cuerpo.innerHTML = `<div class="ab-aviso"><i class="ri-book-2-line"></i>Este libro se lee en tu dispositivo.<br><br>
                <button type="button" class="ab-btn" id="ab-bajar-epub"><i class="ri-download-2-line"></i> Descargar ${esc(d.formato)}</button></div>`;
            cuerpo.querySelector('#ab-bajar-epub').addEventListener('click', descargar);
        } else if (d.url) {
            cuerpo.innerHTML = `<div class="ab-aviso"><i class="ri-external-link-line"></i>Este material está en otra página.<br><br>
                <a class="ab-btn" href="${esc(d.url)}" target="_blank" rel="noopener noreferrer"><i class="ri-external-link-line"></i> Abrir enlace</a></div>`;
        } else {
            cuerpo.innerHTML = '<div class="ab-aviso"><i class="ri-file-unknow-line"></i>Este material no tiene contenido para mostrar.</div>';
        }
    }

    $('#ab-volver').addEventListener('click', () => { cerrarRecurso(); mostrar(pestanaAntes); CARGAS[pestanaAntes](); });

    $('#ab-favorito').addEventListener('click', async () => {
        if (!actual) return;
        const nuevo = !$('#ab-favorito').dataset.fav;
        pintarFavorito(nuevo);
        try {
            await api(`/recursos/${actual.id}/favorito`, { metodo: 'PUT', datos: { favorito: nuevo } });
            alerta(nuevo ? 'Agregado a favoritos.' : 'Quitado de favoritos.');
        } catch (e) { pintarFavorito(!nuevo); fallo(e); }
    });

    $('#ab-terminado').addEventListener('click', async () => {
        if (!actual) return;
        try {
            const p = await api(`/recursos/${actual.id}/progreso`, { metodo: 'POST', datos: { evento: 'avance', porcentaje: 100 } });
            pintarPorcentaje(p.marcador?.porcentaje);
            alerta('¡Bien! Lo marcamos como terminado.');
        } catch (e) { fallo(e); }
    });

    async function enlaces() {
        return api(`/recursos/${actual.id}/enlace`);
    }
    async function descargar() {
        if (!actual) return;
        try { window.location.href = (await enlaces()).descargar; } catch (e) { fallo(e); }
    }
    $('#ab-descargar').addEventListener('click', descargar);

    // ── Partida o apertura (SCRUM-37) ───────────────
    function abrirPartida(cuerpo, d, m) {
        cuerpo.innerHTML = '<div id="ab-visor"></div>';
        visor = window.EgauVisor.crear(cuerpo.querySelector('#ab-visor'), {
            alMover: (jugada, total) => {
                if (!actual || jugada === 0) return;
                guardarAvance({ jugada, porcentaje: total ? Math.round(jugada / total * 100) : 100 });
            },
        });
        visor.cargar(d, (m.porcentaje || 0) >= 100 ? 0 : (m.jugada || 0));   // terminada: empieza de nuevo
        limpiar.push(() => { visor?.destruir(); visor = null; });
    }

    // ── Lector de PDF (SCRUM-38) ────────────────────
    let promesaPdfjs = null;
    function cargarPdfjs() {
        if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
        promesaPdfjs = promesaPdfjs || new Promise((ok, mal) => {
            const s = document.createElement('script');
            s.src = PDFJS + 'pdf.min.js';
            s.onload = () => { window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS + 'pdf.worker.min.js'; ok(window.pdfjsLib); };
            s.onerror = () => { promesaPdfjs = null; mal(new Error('No se pudo cargar el lector de PDF.')); };
            document.head.appendChild(s);
        });
        return promesaPdfjs;
    }

    async function abrirPdf(cuerpo, d, m) {
        cuerpo.innerHTML = `
            <div class="ab-pdf-barra">
                <button type="button" class="ab-btn-sec" data-pdf="ant" aria-label="Página anterior"><i class="ri-arrow-left-s-line"></i></button>
                <span>Página <input type="number" min="1" value="1" id="ab-pdf-pagina" aria-label="Página"> de <b id="ab-pdf-total">…</b></span>
                <button type="button" class="ab-btn-sec" data-pdf="sig" aria-label="Página siguiente"><i class="ri-arrow-right-s-line"></i></button>
                <button type="button" class="ab-btn-sec" data-pdf="menos" aria-label="Alejar"><i class="ri-zoom-out-line"></i></button>
                <button type="button" class="ab-btn-sec" data-pdf="mas" aria-label="Acercar"><i class="ri-zoom-in-line"></i></button>
            </div>
            <div class="ab-pdf-hoja"><div class="ab-aviso"><i class="ri-loader-4-line"></i>Abriendo el libro...</div></div>`;
        const hoja = cuerpo.querySelector('.ab-pdf-hoja');
        const inputPag = cuerpo.querySelector('#ab-pdf-pagina');
        let doc = null, pag = 1, zoom = 1, tarea = null, vivo = true;
        limpiar.push(() => { vivo = false; tarea?.cancel(); doc?.destroy(); });

        try {
            const [pdfjs, enl] = await Promise.all([cargarPdfjs(), enlaces()]);
            if (!vivo) return;
            doc = await pdfjs.getDocument({ url: enl.ver, withCredentials: true }).promise;
            if (!vivo) { doc.destroy(); return; }
        } catch (e) {
            if (!vivo) return;
            hoja.innerHTML = `<div class="ab-aviso"><i class="ri-error-warning-line"></i>${esc(e.message || 'No se pudo abrir el PDF.')}<br><br>
                <button type="button" class="ab-btn" id="ab-pdf-bajar"><i class="ri-download-2-line"></i> Descargar PDF</button></div>`;
            hoja.querySelector('#ab-pdf-bajar').addEventListener('click', descargar);
            return;
        }
        cuerpo.querySelector('#ab-pdf-total').textContent = doc.numPages;
        inputPag.max = doc.numPages;
        const canvas = document.createElement('canvas');
        hoja.innerHTML = '';
        hoja.appendChild(canvas);

        async function pintar(n, guardar = true) {
            pag = Math.max(1, Math.min(doc.numPages, n));
            inputPag.value = pag;
            cuerpo.querySelector('[data-pdf="ant"]').disabled = pag === 1;
            cuerpo.querySelector('[data-pdf="sig"]').disabled = pag === doc.numPages;
            const page = await doc.getPage(pag);
            if (!vivo) return;
            const ancho = Math.min(900, Math.max(280, hoja.clientWidth - 32));
            const base = page.getViewport({ scale: 1 });
            const escala = Math.min(2.5, ancho / base.width) * zoom;
            const vp = page.getViewport({ scale: escala * (window.devicePixelRatio || 1) });
            canvas.width = vp.width; canvas.height = vp.height;
            canvas.style.width = Math.floor(vp.width / (window.devicePixelRatio || 1)) + 'px';
            tarea?.cancel();
            tarea = page.render({ canvasContext: canvas.getContext('2d'), viewport: vp });
            try { await tarea.promise; } catch { /* cancelada por otra página */ }
            hoja.scrollTop = 0;
            if (guardar) guardarAvance({ pagina: pag, porcentaje: Math.round(pag / doc.numPages * 100) });
        }

        cuerpo.querySelector('.ab-pdf-barra').addEventListener('click', (e) => {
            const b = e.target.closest('[data-pdf]');
            if (!b || b.disabled) return;
            if (b.dataset.pdf === 'ant') pintar(pag - 1);
            if (b.dataset.pdf === 'sig') pintar(pag + 1);
            if (b.dataset.pdf === 'mas') { zoom = Math.min(3, zoom + 0.25); pintar(pag, false); }
            if (b.dataset.pdf === 'menos') { zoom = Math.max(0.5, zoom - 0.25); pintar(pag, false); }
        });
        inputPag.addEventListener('change', () => pintar(Number(inputPag.value) || 1));
        const teclas = (e) => {
            if (!vivo || hoja.offsetParent === null || e.target.closest('input, textarea, select')) return;
            if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); pintar(pag + 1); }
            if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); pintar(pag - 1); }
        };
        document.addEventListener('keydown', teclas);
        limpiar.push(() => document.removeEventListener('keydown', teclas));

        const inicial = Number(m.pagina) || 1;
        await pintar(inicial, false);
        if (inicial > 1) alerta(`Seguimos en la página ${inicial}, donde te quedaste.`);
    }

    // ── Video ───────────────────────────────────────
    async function abrirVideo(cuerpo, d, m) {
        cuerpo.innerHTML = '<div class="ab-aviso"><i class="ri-loader-4-line"></i>Cargando video...</div>';
        let enl;
        try { enl = await enlaces(); } catch (e) { return fallo(e); }
        if (!actual || actual.id !== d.id) return;
        cuerpo.innerHTML = `<video class="ab-video" controls preload="metadata" src="${esc(enl.ver)}"></video>`;
        const v = cuerpo.querySelector('video');
        let ultimo = 0;
        v.addEventListener('loadedmetadata', () => { if (m.segundo && m.segundo < v.duration - 5) v.currentTime = m.segundo; }, { once: true });
        const guardar = () => {
            if (!v.duration) return;
            guardarAvance({ segundo: Math.floor(v.currentTime), porcentaje: Math.round(v.currentTime / v.duration * 100) });
        };
        v.addEventListener('timeupdate', () => { if (Math.abs(v.currentTime - ultimo) >= 15) { ultimo = v.currentTime; guardar(); } });
        v.addEventListener('pause', guardar);
        v.addEventListener('ended', () => guardarAvance({ segundo: Math.floor(v.duration), porcentaje: 100 }));
        limpiar.push(() => { v.pause(); v.removeAttribute('src'); v.load(); });
    }

    // ── Carga al entrar a la sección ────────────────
    let cargada = false;
    document.querySelectorAll('.nav-item[data-section="biblioteca"]').forEach(n => n.addEventListener('click', () => {
        if (!cargada) { cargada = true; cargarInicio(); }
        else if (pestana === 'inicio' && document.querySelector('[data-ab-vista="recurso"]').hidden) cargarInicio();
    }));
    document.querySelectorAll('.nav-item:not([data-section="biblioteca"])').forEach(n => n.addEventListener('click', () => {
        // al salir de la biblioteca se pausa el video y se guarda el avance
        if (actual) { cerrarRecurso(); mostrar(pestanaAntes); }
    }));
});
