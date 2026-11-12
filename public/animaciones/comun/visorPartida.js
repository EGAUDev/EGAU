// ==============================================
//  visorPartida.js — Visor de partidas (SCRUM-37)
//  Lo usan el portal del alumno y el panel del personal.
//
//  - Tablero desde las FEN que manda el servidor (ya validadas).
//  - Voltear tablero, teclado (← → Inicio Fin, F voltea).
//  - Variantes: se muestran entre paréntesis y se pueden recorrer.
//  - Exportar PGN: descargar archivo o copiar al portapapeles.
//
//  Uso:
//    const v = EgauVisor.crear(contenedor, { alMover: (jugada, total) => {} });
//    v.cargar(partida, jugadaInicial);   // partida = respuesta de /recursos/{id}
//    v.destruir();
// ==============================================
(function () {
    const PIEZAS = {
        K: '♔', Q: '♕', R: '♖', B: '♗', N: '♘', P: '♙',
        k: '♚', q: '♛', r: '♜', b: '♝', n: '♞', p: '♟',
    };
    const INICIAL = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    function tableroDeFen(fen) {
        return String(fen || INICIAL).split(' ')[0].split('/').map(fila => {
            const out = [];
            for (const c of fila) {
                if (/\d/.test(c)) for (let i = 0; i < Number(c); i++) out.push(null);
                else out.push(c);
            }
            return out;
        });
    }
    // Casillas que cambiaron entre dos posiciones (para marcar la última jugada)
    function cambio(antes, despues) {
        const dif = [];
        for (let r = 0; r < 8; r++) for (let f = 0; f < 8; f++) if (antes[r][f] !== despues[r][f]) dif.push({ f, r, vacia: !despues[r][f] });
        const desde = dif.find(d => d.vacia) || dif[0];
        const hasta = dif.find(d => !d.vacia) || dif[1] || dif[0];
        return desde ? { desde, hasta } : {};
    }
    // Medias jugadas antes de la primera, según la FEN inicial
    function plyBase(fen) {
        const c = String(fen || INICIAL).split(' ');
        return (Math.max(1, Number(c[5]) || 1) - 1) * 2 + (c[1] === 'b' ? 1 : 0);
    }
    function preparar(fens) {
        return fens.map((fen, i) => {
            const tab = tableroDeFen(fen);
            return i === 0 ? { tab } : { tab, ...cambio(tableroDeFen(fens[i - 1]), tab) };
        });
    }

    function crear(raiz, { alMover = null, idBase = 'egau-visor' } = {}) {
        raiz.classList.add('ev-visor');
        raiz.tabIndex = 0;
        raiz.innerHTML = `
            <div class="ev-zona-tablero">
                <div class="ev-marco">
                    <div class="ev-rangos"></div>
                    <div class="ev-tablero" role="img" aria-label="Tablero"></div>
                    <div></div>
                    <div class="ev-columnas"></div>
                </div>
                <div class="ev-controles">
                    <button type="button" class="ev-btn" data-ev="inicio" title="Inicio (tecla Inicio)"><i class="ri-skip-back-line"></i></button>
                    <button type="button" class="ev-btn" data-ev="ant" title="Anterior (←)"><i class="ri-arrow-left-s-line"></i></button>
                    <span class="ev-pos">Inicio</span>
                    <button type="button" class="ev-btn" data-ev="sig" title="Siguiente (→)"><i class="ri-arrow-right-s-line"></i></button>
                    <button type="button" class="ev-btn" data-ev="fin" title="Final (tecla Fin)"><i class="ri-skip-forward-line"></i></button>
                    <button type="button" class="ev-btn" data-ev="voltear" title="Voltear tablero (F)"><i class="ri-arrow-up-down-line"></i></button>
                </div>
                <div class="ev-variante-aviso" hidden>
                    <span>Estás viendo una variante</span>
                    <button type="button" class="ev-link" data-ev="principal">Volver a la partida</button>
                </div>
                <p class="ev-ayuda">Usa ← → para moverte y F para voltear el tablero.</p>
            </div>
            <div class="ev-zona-jugadas">
                <div class="ev-jugadas" aria-label="Jugadas"></div>
                <div class="ev-exportar">
                    <button type="button" class="ev-btn-texto" data-ev="descargar"><i class="ri-download-2-line"></i> Descargar PGN</button>
                    <button type="button" class="ev-btn-texto" data-ev="copiar"><i class="ri-file-copy-line"></i> Copiar PGN</button>
                </div>
            </div>`;

        const $ = (s) => raiz.querySelector(s);
        let partida = null;
        let principal = [];     // posiciones de la línea principal
        let variantes = [];     // [{ desde, movs, pos }]
        let linea = null;       // { tipo: 'p' } o { tipo: 'v', n }
        let idx = -1;           // jugada actual dentro de la línea (-1 = posición inicial)
        let volteado = false;
        let base = 0;

        const posActuales = () => linea.tipo === 'p' ? principal : variantes[linea.n].pos;
        const totalLinea = () => posActuales().length - 1;

        function pintarCoordenadas() {
            const rangos = [8, 7, 6, 5, 4, 3, 2, 1], cols = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
            if (volteado) { rangos.reverse(); cols.reverse(); }
            $('.ev-rangos').innerHTML = rangos.map(r => `<span>${r}</span>`).join('');
            $('.ev-columnas').innerHTML = cols.map(c => `<span>${c}</span>`).join('');
        }

        function pintar() {
            const pos = posActuales()[idx + 1];
            let html = '';
            for (let i = 0; i < 8; i++) {
                for (let j = 0; j < 8; j++) {
                    const r = volteado ? 7 - i : i, f = volteado ? 7 - j : j;
                    const clara = (r + f) % 2 === 0;
                    const marcada = pos.desde && ((pos.desde.f === f && pos.desde.r === r) || (pos.hasta.f === f && pos.hasta.r === r));
                    const p = pos.tab[r][f];
                    html += `<div class="ev-casilla ${clara ? 'ev-clara' : 'ev-oscura'} ${marcada ? 'ev-marcada' : ''}">${p ? `<span class="ev-pieza ${p === p.toUpperCase() ? 'ev-blanca' : 'ev-negra'}">${PIEZAS[p]}</span>` : ''}</div>`;
                }
            }
            $('.ev-tablero').innerHTML = html;

            const total = totalLinea();
            const offset = linea.tipo === 'v' ? variantes[linea.n].desde - 1 : 0;
            $('.ev-pos').textContent = idx === -1 ? (linea.tipo === 'v' ? 'Inicio de la variante' : 'Inicio') : `Mov. ${offset + idx + 1} / ${linea.tipo === 'v' ? offset + total : total}`;
            $('[data-ev="inicio"]').disabled = idx === -1;
            $('[data-ev="ant"]').disabled = idx === -1;
            $('[data-ev="sig"]').disabled = idx >= total - 1;
            $('[data-ev="fin"]').disabled = idx >= total - 1;
            $('.ev-variante-aviso').hidden = linea.tipo !== 'v';

            raiz.querySelectorAll('.ev-mov').forEach(b => b.classList.remove('active'));
            const clave = linea.tipo === 'p' ? `p:${idx}` : `v${linea.n}:${idx}`;
            const activo = raiz.querySelector(`.ev-mov[data-m="${clave}"]`);
            if (activo) {
                activo.classList.add('active');
                const caja = $('.ev-jugadas');
                const top = activo.offsetTop - caja.offsetTop;
                if (top < caja.scrollTop || top > caja.scrollTop + caja.clientHeight - 30) caja.scrollTop = top - caja.clientHeight / 2;
            }
            if (alMover && linea.tipo === 'p') alMover(idx + 1, principal.length - 1);
        }

        function ir(i, nuevaLinea = null) {
            if (nuevaLinea) linea = nuevaLinea;
            idx = Math.max(-1, Math.min(totalLinea() - 1, i));
            pintar();
        }

        function textoJugadas() {
            const movs = partida.movimientos || [];
            const porJugada = {};
            variantes.forEach((v, n) => { (porJugada[v.desde] = porJugada[v.desde] || []).push(n); });
            const num = (ply, forzar) => {
                const n = Math.floor(ply / 2) + 1;
                return ply % 2 === 0 ? `<span class="ev-num">${n}.</span>` : (forzar ? `<span class="ev-num">${n}...</span>` : '');
            };
            let html = '', despuesDeVariante = false;
            movs.forEach((m, i) => {
                html += num(base + i, i === 0 || despuesDeVariante) + `<button type="button" class="ev-mov" data-m="p:${i}">${esc(m)}</button> `;
                despuesDeVariante = false;
                (porJugada[i + 1] || []).forEach(n => {
                    const v = variantes[n];
                    html += '<span class="ev-variante">(' + v.movs.map((vm, k) =>
                        num(base + v.desde - 1 + k, k === 0) + `<button type="button" class="ev-mov" data-m="v${n}:${k}">${esc(vm)}</button>`).join(' ') + ')</span> ';
                    despuesDeVariante = true;
                });
            });
            html += `<span class="ev-resultado">${esc(partida.resultado || '*')}</span>`;
            return html;
        }

        function cargar(p, jugadaInicial = 0) {
            partida = p;
            const fens = p.fens && p.fens.length ? p.fens : [p.fen_final || INICIAL];
            base = plyBase(fens[0]);
            principal = preparar(fens);
            // fens de la variante: [posición antes, después de cada jugada]
            variantes = (p.variantes || []).map(v => ({ desde: v.desde, movs: v.jugadas_es || v.jugadas || [], pos: preparar(v.fens || []) }))
                .filter(v => v.pos.length > 1);
            $('.ev-jugadas').innerHTML = textoJugadas();
            $('.ev-exportar').hidden = !p.pgn;
            linea = { tipo: 'p' };
            pintarCoordenadas();
            ir(Math.min(Number(jugadaInicial) || 0, principal.length - 1) - 1);
        }

        function nombreArchivo() {
            const t = (partida.blancas ? `${partida.blancas} vs ${partida.negras || ''}` : partida.titulo || 'partida');
            return t.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^\w\- ]+/g, '').trim().replace(/\s+/g, '_').slice(0, 80) + '.pgn';
        }

        raiz.addEventListener('click', async (e) => {
            const mov = e.target.closest('.ev-mov');
            if (mov) {
                const [l, i] = mov.dataset.m.split(':');
                return ir(Number(i), l === 'p' ? { tipo: 'p' } : { tipo: 'v', n: Number(l.slice(1)) });
            }
            const b = e.target.closest('[data-ev]');
            if (!b || !partida) return;
            switch (b.dataset.ev) {
                case 'inicio': ir(-1); break;
                case 'ant': ir(idx - 1); break;
                case 'sig': ir(idx + 1); break;
                case 'fin': ir(totalLinea() - 1); break;
                case 'voltear': volteado = !volteado; pintarCoordenadas(); pintar(); break;
                case 'principal': {
                    const v = variantes[linea.n];
                    ir(v.desde - 2, { tipo: 'p' });
                    break;
                }
                case 'descargar': {
                    const url = URL.createObjectURL(new Blob([partida.pgn + '\n'], { type: 'application/x-chess-pgn' }));
                    const a = Object.assign(document.createElement('a'), { href: url, download: nombreArchivo() });
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                    break;
                }
                case 'copiar':
                    try {
                        await navigator.clipboard.writeText(partida.pgn);
                        window.egauAlert?.('PGN copiado.');
                    } catch {
                        window.egauAlert?.('No se pudo copiar. Usa "Descargar PGN".', 'warning');
                    }
                    break;
            }
        });

        // Teclado: solo cuando el visor está a la vista y no se está escribiendo
        function teclado(e) {
            if (!partida || raiz.offsetParent === null) return;
            if (e.target.closest('input, textarea, select, [contenteditable="true"]')) return;
            if (e.altKey || e.ctrlKey || e.metaKey) return;
            const acciones = { ArrowLeft: () => ir(idx - 1), ArrowRight: () => ir(idx + 1), Home: () => ir(-1), End: () => ir(totalLinea() - 1),
                f: () => { volteado = !volteado; pintarCoordenadas(); pintar(); }, F: () => { volteado = !volteado; pintarCoordenadas(); pintar(); } };
            const fn = acciones[e.key];
            if (fn) { e.preventDefault(); fn(); }
        }
        document.addEventListener('keydown', teclado);

        return {
            cargar,
            jugada: () => (linea && linea.tipo === 'p' ? idx + 1 : null),
            destruir() { document.removeEventListener('keydown', teclado); raiz.innerHTML = ''; partida = null; },
        };
    }

    window.EgauVisor = { crear, tableroDeFen };
})();
