// ==============================================
//  comun/buscador.js — Búsqueda común de todas las tablas
//  - Espera 350 ms después de la última tecla (debounce)
//  - Cancela la petición anterior si llega una nueva (AbortController)
//  - Paginación en el servidor (?page=&per_page=) con botones « 1 … 4 5 6 … 20 »
//  EGAU Chess | AMAAC
// ==============================================

window.EgauBuscador = (() => {

    /** Minúsculas y sin acentos: "José" → "jose". Para filtrar en el navegador. */
    const sinAcentos = (texto) => (texto ?? '').toString()
        .normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

    /** ¿El texto contiene todas las palabras de la búsqueda (sin importar acentos ni orden)? */
    const coincide = (texto, q) => {
        const t = sinAcentos(texto);
        return sinAcentos(q).split(/\s+/).filter(Boolean).every(p => t.includes(p));
    };

    /** Escapa texto para meterlo en HTML. */
    const html = (texto) => (texto ?? '').toString()
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    const debounce = (fn, ms = 350) => {
        let t = null;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    };

    /** Números a mostrar: siempre la primera, la última y dos alrededor de la actual. */
    const numeros = (pagina, paginas) => {
        const lista = [];
        for (let i = 1; i <= paginas; i++) {
            if (i === 1 || i === paginas || Math.abs(i - pagina) <= 2) lista.push(i);
            else if (lista[lista.length - 1] !== '…') lista.push('…');
        }
        return lista;
    };

    /** Pinta los botones de página dentro de "contenedor". */
    const pintarPaginador = (contenedor, meta, irA) => {
        if (!contenedor) return;
        contenedor.innerHTML = '';
        const pagina = meta?.page ?? 1;
        const paginas = meta?.pages ?? 1;
        if (paginas <= 1) return;

        const boton = (contenido, destino, { activo = false, deshabilitado = false } = {}) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'pag-btn' + (activo ? ' active' : '');
            b.innerHTML = contenido;
            b.disabled = deshabilitado;
            if (!deshabilitado && !activo) b.addEventListener('click', () => irA(destino));
            contenedor.appendChild(b);
        };

        boton('<i class="ri-arrow-left-s-line"></i>', pagina - 1, { deshabilitado: pagina <= 1 });
        numeros(pagina, paginas).forEach(n => {
            if (n === '…') boton('…', null, { deshabilitado: true });
            else boton(String(n), n, { activo: n === pagina });
        });
        boton('<i class="ri-arrow-right-s-line"></i>', pagina + 1, { deshabilitado: pagina >= paginas });
    };

    /** "Mostrando 26–50 de 312 resultados" */
    const textoInfo = (meta, cantidad, palabra = 'resultado') => {
        const total = meta?.total ?? cantidad;
        const plural = total === 1 ? palabra : palabra + (/[aeiou]$/i.test(palabra) ? 's' : 'es');
        if (!meta || (meta.pages ?? 1) <= 1) return `Mostrando ${total} ${plural}`;
        const desde = (meta.page - 1) * meta.per_page + 1;
        const hasta = desde + cantidad - 1;
        return `Mostrando ${desde}–${hasta} de ${total} ${plural}`;
    };

    /**
     * Crea un buscador para una tabla.
     *  url        '/admin/buscar/alumno'
     *  params     () => ({ q, id_sede, ... })      filtros actuales
     *  entrada    <input> de búsqueda (opcional): al escribir vuelve a la página 1
     *  porPagina  25 por defecto; null = sin paginar (trae todo)
     *  botones    contenedor de los botones de página (opcional)
     *  info       elemento para "Mostrando ..." (opcional)
     *  palabra    'resultado', 'factura', ... para el texto de info
     *  alRecibir  (res) => {}  pinta la tabla con res.data
     *  alFallar   (err) => {}
     */
    const crear = (op) => {
        const estado = { pagina: 1, meta: null, controlador: null };

        const cargar = () => {
            if (estado.controlador) estado.controlador.abort();
            const controlador = new AbortController();
            estado.controlador = controlador;

            const filtros = Object.entries(op.params ? op.params() : {})
                .filter(([, v]) => v !== '' && v !== null && v !== undefined);
            const params = new URLSearchParams(filtros);
            if (op.porPagina !== null) {
                params.set('page', estado.pagina);
                params.set('per_page', op.porPagina ?? 25);
            }

            return fetch(`${op.url}?${params}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                signal: controlador.signal,
            })
                .then(r => r.json())
                .then(res => {
                    if (estado.controlador === controlador) estado.controlador = null;
                    if (res.error) throw new Error(res.error);
                    estado.meta = res.meta ?? null;
                    if (estado.meta) estado.pagina = estado.meta.page;
                    op.alRecibir && op.alRecibir(res);
                    if (op.info) op.info.textContent = textoInfo(estado.meta, (res.data ?? []).length, op.palabra);
                    pintarPaginador(op.botones, estado.meta, irA);
                    return res;
                })
                .catch(err => {
                    if (err.name === 'AbortError') return;
                    console.error(`Error al buscar (${op.url}):`, err);
                    op.alFallar && op.alFallar(err);
                });
        };

        const irA = (pagina) => { estado.pagina = Math.max(1, pagina); return cargar(); };

        /** Vuelve a la página 1 (cuando cambia el texto o un filtro). */
        const reiniciar = () => irA(1);

        if (op.entrada) op.entrada.addEventListener('input', debounce(reiniciar, op.espera ?? 350));

        return { cargar, irA, reiniciar, estado };
    };

    return { crear, debounce, sinAcentos, coincide, html, pintarPaginador, textoInfo };
})();
