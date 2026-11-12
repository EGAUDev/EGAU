/**
 * dashboardAdminFacturas.js
 * EGAU Chess — Gestión de Facturas
 */
(function () {
    'use strict';

    // ── Estado ───────────────────────────────────────────────
    const state = {
        facturas: [],
        filtros: { id_sede: '', vigencia: '', tipo: '', q: '' },
        paginaActual: 1,
        porPagina: 15,
        idFacturaActiva: null,
    };

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const PERMISOS = window.PERMISOS_PAGOS || { ver: false, edit: false, admin: false };

    // ── Helpers DOM ──────────────────────────────────────────
    const $ = (id) => document.getElementById(id);
    const el = (sel, ctx = document) => ctx.querySelector(sel);

    // ── Referencias ──────────────────────────────────────────
    let tbody, paginaInfo, paginaBtns, detailPanel, detailEmpty, detailContent, idFacturaHidden;

    function init() {
        tbody        = $('tbody-facturas');
        paginaInfo   = $('facturas-pag-info');
        paginaBtns   = $('facturas-pag-btns');
        detailPanel  = $('factura-detail-panel');
        detailEmpty  = $('factura-detail-empty');
        detailContent= $('factura-detail-content');
        idFacturaHidden = $('fd-id-factura');

        if (!tbody) return; // sección no visible

        initDropdowns();
        bindFiltros();
        cargarFacturas();
        bindDetailButtons();
    }

    // ── Dropdowns de filtros ─────────────────────────────────
    function initDropdowns() {
        document.querySelectorAll('#section-pagos .custom-dropdown').forEach(dd => {
            const trigger = el('.custom-select-trigger', dd);
            const opts    = el('.custom-options-container', dd);
            if (!trigger || !opts) return;

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const open = dd.classList.contains('open');
                document.querySelectorAll('#section-pagos .custom-dropdown.open').forEach(o => o.classList.remove('open'));
                if (!open) dd.classList.add('open');
            });

            opts.querySelectorAll('.custom-option').forEach(opt => {
                opt.addEventListener('click', () => {
                    const val = opt.dataset.value;
                    const spanText = el('.selected-text', trigger);
                    spanText.textContent = opt.textContent.trim();
                    spanText.dataset.value = val;
                    opts.querySelectorAll('.custom-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    dd.classList.remove('open');
                    aplicarFiltros();
                });
            });
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('#section-pagos .custom-dropdown.open').forEach(o => o.classList.remove('open'));
        });
    }

    function bindFiltros() {
        const buscador = $('buscador-facturas');
        if (buscador) {
            let t;
            buscador.addEventListener('input', () => {
                clearTimeout(t);
                t = setTimeout(aplicarFiltros, 350);
            });
        }

        const btnLimpiar = $('btn-limpiar-facturas');
        if (btnLimpiar) btnLimpiar.addEventListener('click', limpiarFiltros);
    }

    function aplicarFiltros() {
        const buscador = $('buscador-facturas');
        state.filtros.q = buscador ? buscador.value.trim() : '';

        const ddSede = $('dropdown-factura-sede');
        if (ddSede) state.filtros.id_sede = el('.selected-text', ddSede)?.dataset.value || '';

        const ddVig = $('dropdown-factura-vigencia');
        if (ddVig) state.filtros.vigencia = el('.selected-text', ddVig)?.dataset.value || '';

        const ddTipo = $('dropdown-factura-tipo');
        if (ddTipo) state.filtros.tipo = el('.selected-text', ddTipo)?.dataset.value || '';

        state.paginaActual = 1;
        cargarFacturas();
    }

    function limpiarFiltros() {
        const buscador = $('buscador-facturas');
        if (buscador) buscador.value = '';

        document.querySelectorAll('#section-pagos .custom-dropdown').forEach(dd => {
            const first = el('.custom-option', el('.custom-options-container', dd));
            if (first) {
                el('.selected-text', dd).textContent = first.textContent.trim();
                el('.selected-text', dd).dataset.value = first.dataset.value;
                el('.custom-options-container', dd).querySelectorAll('.custom-option').forEach(o => o.classList.remove('selected'));
                first.classList.add('selected');
            }
        });

        state.filtros = { id_sede: '', vigencia: '', tipo: '', q: '' };
        state.paginaActual = 1;
        cargarFacturas();
    }

    // ── Carga de datos (paginada en el servidor) ─────────────
    let busqueda = null;
    function cargarFacturas() {
        mostrarLoading();
        if (!busqueda) {
            busqueda = window.EgauBuscador.crear({
                url: '/admin/facturas',
                porPagina: state.porPagina,
                palabra: 'factura',
                info: paginaInfo,
                botones: paginaBtns,
                params: () => ({ ...state.filtros }),
                alRecibir: (json) => {
                    state.facturas = json.data || [];
                    state.paginaActual = json.meta?.page ?? 1;
                    renderTabla();
                },
                alFallar: () => {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#c0392b;padding:1.5rem;">Error al cargar las facturas.</td></tr>';
                },
            });
        }
        busqueda.irA(state.paginaActual);
    }

    function mostrarLoading() {
        tbody.innerHTML = `
            <tr id="facturas-loading-row">
                <td colspan="8" style="text-align:center;padding:2rem;color:var(--texto-suave);">
                    <i class="ri-loader-4-line ri-spin" style="font-size:1.5rem;"></i>
                </td>
            </tr>`;
    }

    // ── Renderizado de tabla ─────────────────────────────────
    function renderTabla() {
        const pagina = state.facturas;   // el servidor ya manda solo la página actual

        if (pagina.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--texto-suave);">No se encontraron facturas.</td></tr>';
            return;
        }

        tbody.innerHTML = pagina.map(f => {
            const vigBadge  = badgeVigencia(f.vigencia);
            const tipoBadge = badgeTipo(f.tipo);
            const total     = f.total_pago != null ? `$${parseFloat(f.total_pago).toFixed(2)}` : '—';
            const limite    = f.fecha_limite ? new Date(f.fecha_limite).toLocaleDateString('es-MX') : '—';
            const activa    = f.id_factura == state.idFacturaActiva ? 'fila-activa' : '';
            return `
                <tr class="${activa}" data-id="${f.id_factura}" style="cursor:pointer;">
                    <td>#${f.id_factura}</td>
                    <td>${esc(f.nombre_alumno || '—')}</td>
                    <td>${esc(f.concepto || '—')}</td>
                    <td>${total}</td>
                    <td>${tipoBadge}</td>
                    <td>${vigBadge}</td>
                    <td>${limite}</td>
                    <td>
                        <button class="btn-icon btn-ver btn-ver-factura" data-id="${f.id_factura}" title="Ver detalle">
                            <i class="ri-eye-line"></i>
                        </button>
                    </td>
                </tr>`;
        }).join('');

        // Listeners de fila
        tbody.querySelectorAll('tr[data-id]').forEach(row => {
            row.addEventListener('click', (e) => {
                if (!e.target.closest('.btn-ver-factura')) {
                    cargarDetalle(parseInt(row.dataset.id));
                }
            });
        });
        tbody.querySelectorAll('.btn-ver-factura').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                cargarDetalle(parseInt(btn.dataset.id));
            });
        });
    }

    function badgeVigencia(v) {
        const map = {
            pagado:    ['badge-vigencia-pagado',    'Pagado'],
            enproceso: ['badge-vigencia-enproceso',  'En Proceso'],
            cancelado: ['badge-vigencia-cancelado',  'Cancelado'],
            expirado:  ['badge-vigencia-expirado',   'Expirado'],
        };
        const [cls, label] = map[v] || ['badge', v || '—'];
        return `<span class="badge ${cls}">${label}</span>`;
    }

    function badgeTipo(t) {
        if (t === 'grupo')        return `<span class="badge badge-tipo-grupo">Grupo</span>`;
        if (t === 'extraescolar') return `<span class="badge badge-tipo-extraescolar">Extraescolar</span>`;
        return `<span class="badge">—</span>`;
    }

    // ── Detalle de factura ───────────────────────────────────
    function cargarDetalle(idFactura) {
        state.idFacturaActiva = idFactura;
        tbody.querySelectorAll('tr[data-id]').forEach(r => r.classList.toggle('fila-activa', parseInt(r.dataset.id) === idFactura));

        if (detailEmpty) detailEmpty.style.display = 'none';
        if (detailContent) detailContent.style.display = 'none';

        fetch(`/admin/facturas/${idFactura}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
        })
        .then(r => r.json())
        .then(f => llenarDetalle(f))
        .catch(() => alert('Error al cargar el detalle de la factura.'));
    }

    function llenarDetalle(f) {
        setText('fd-concepto',    f.concepto || '—');
        setText('fd-alumno',      f.nombre_alumno || '—');
        setText('fd-email',       f.email_alumno || '—');
        setText('fd-sede',        f.nombre_sede || '—');
        setText('fd-total',       f.total_pago != null ? `$${parseFloat(f.total_pago).toFixed(2)}` : '—');
        setText('fd-emision',     f.fecha_emicion ? new Date(f.fecha_emicion).toLocaleString('es-MX') : '—');
        setText('fd-limite',      f.fecha_limite  ? new Date(f.fecha_limite).toLocaleString('es-MX')  : '—');
        setText('fd-descripcion', f.descripcion || 'Sin descripción.');

        const tipo = f.id_inscripcion ? 'grupo' : (f.id_inscripcionextra ? 'extraescolar' : 'otro');
        const tipoBadgeEl = $('fd-tipo-badge');
        if (tipoBadgeEl) tipoBadgeEl.outerHTML = badgeTipo(tipo).replace('badge', 'badge fd-tipo-badge');

        const vigEl = $('fd-vigencia-badge');
        if (vigEl) vigEl.outerHTML = badgeVigencia(f.vigencia).replace('class="badge', 'class="badge fd-vigencia-badge');

        if (idFacturaHidden) idFacturaHidden.value = f.id_factura;

        if (detailEmpty)  detailEmpty.style.display  = 'none';
        if (detailContent) detailContent.style.display = 'block';
    }

    function setText(id, text) {
        const el = $(id);
        if (el) el.textContent = text;
    }

    // ── Botones de vigencia ──────────────────────────────────
    function bindDetailButtons() {
        const btnPagado    = $('btn-marcar-pagado');
        const btnCancelado = $('btn-marcar-cancelado');

        if (btnPagado)    btnPagado.addEventListener('click',    () => cambiarVigencia('pagado'));
        if (btnCancelado) btnCancelado.addEventListener('click', () => cambiarVigencia('cancelado'));
    }

    function cambiarVigencia(vigencia) {
        const idFactura = idFacturaHidden?.value;
        if (!idFactura) return;

        const etiqueta = vigencia === 'pagado' ? 'Pagado' : 'Cancelado';
        if (!confirm(`¿Marcar esta factura como "${etiqueta}"? Esta acción no se puede deshacer fácilmente.`)) return;

        fetch(`/admin/facturas/${idFactura}/vigencia`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify({ vigencia })
        })
        .then(r => r.json())
        .then(res => {
            if (res.error) { alert(res.error); return; }
            // Actualizar en el estado local
            const f = state.facturas.find(x => x.id_factura == idFactura);
            if (f) f.vigencia = vigencia;
            renderTabla();
            // Re-cargar detalle
            cargarDetalle(parseInt(idFactura));
        })
        .catch(() => alert('Error al actualizar la vigencia.'));
    }

    // ── Utilidad ─────────────────────────────────────────────
    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Boot ─────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        // Esperar a que la sección esté visible para inicializar
        const navItem = document.querySelector('.nav-item[data-section="pagos"]');
        if (navItem) {
            navItem.addEventListener('click', () => {
                if (!tbody) init();
            });
        }
        // Si ya está activa al cargar
        const seccion = $('section-pagos');
        if (seccion && seccion.style.display !== 'none') {
            init();
        }
        // Observer por si se activa desde el sidebar
        if (seccion) {
            const obs = new MutationObserver(() => {
                if (seccion.style.display !== 'none' && !tbody) init();
            });
            obs.observe(seccion, { attributes: true, attributeFilter: ['style'] });
        }
    });

})();
