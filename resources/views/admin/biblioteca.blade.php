{{-- ===================== SECCIÓN: BIBLIOTECA VIRTUAL (SCRUM-18) ===================== --}}
{{-- Visible para todo el personal, sin depender de permisos. --}}
{{-- El contenido lo pinta public/animaciones/admin/dashboardAdminBiblioteca.js --}}
<section class="section-content" id="section-biblioteca" style="display: none;">

    <div class="section-header">
        <div>
            <h1 class="section-title">Biblioteca Virtual</h1>
            <p class="bib-subtitulo">Libros, partidas comentadas, videos y ejercicios de la academia EGAU.</p>
        </div>
    </div>

    {{-- Pestañas --}}
    <nav class="bib-tabs">
        <button class="bib-tab active" data-vista="catalogo"><i class="ri-book-open-line"></i> Catálogo</button>
        <button class="bib-tab" data-vista="partidas"><i class="ri-sword-line"></i> Partidas</button>
        <button class="bib-tab" data-vista="guardados"><i class="ri-bookmark-line"></i> Mis guardados</button>
        <button class="bib-tab" data-vista="historial"><i class="ri-time-line"></i> Historial</button>
        <button class="bib-tab" data-vista="gestion"><i class="ri-folder-open-line"></i> Gestión de material</button>
    </nav>

    {{-- ─────────────── CATÁLOGO ─────────────── --}}
    <div class="bib-vista" data-vista="catalogo">
        <div class="bib-filtros">
            <div class="bib-buscador">
                <i class="ri-search-line"></i>
                <input type="text" id="bib-buscar" placeholder="Buscar por título, autor o tema..." autocomplete="off">
            </div>
            <select id="bib-filtro-nivel" class="bib-select"><option value="">Todos los niveles</option></select>
            <select id="bib-filtro-tipo" class="bib-select"><option value="">Todos los tipos</option></select>
            <select id="bib-filtro-anio" class="bib-select"><option value="">Todos los años</option></select>
        </div>
        <p class="bib-conteo" id="bib-conteo"></p>
        <div class="bib-grid" id="bib-grid"></div>
        <div class="bib-paginacion" id="bib-paginacion"></div>
    </div>

    {{-- ─────────────── DETALLE DE DOCUMENTO ─────────────── --}}
    <div class="bib-vista" data-vista="documento" style="display: none;">
        <a href="#" class="bib-volver" data-ir="anterior"><i class="ri-arrow-left-line"></i> <span id="bib-doc-volver-texto">Volver al catálogo</span></a>

        <div class="bib-doc">
            <div class="bib-doc-portada" id="bib-doc-portada"></div>
            <div class="bib-doc-info">
                <div class="bib-doc-cabecera">
                    <div>
                        <h2 class="bib-doc-titulo" id="bib-doc-titulo"></h2>
                        <p class="bib-texto-suave" id="bib-doc-creador"></p>
                    </div>
                    <div id="bib-doc-nivel"></div>
                </div>
                <table class="bib-tabla-meta"><tbody id="bib-doc-meta"></tbody></table>
                <div class="bib-doc-acciones">
                    <button class="bib-btn-primario" id="bib-doc-ver"><i class="ri-eye-line"></i> Ver</button>
                    <button class="bib-btn-outline" id="bib-doc-descargar"><i class="ri-download-2-line"></i> Descargar</button>
                    <button class="bib-btn-outline" id="bib-doc-guardar"><i class="ri-bookmark-line"></i> Guardar</button>
                </div>
            </div>
        </div>

        <div id="bib-relacionados-bloque">
            <h3 class="bib-subtitulo-seccion">Material relacionado</h3>
            <div class="bib-grid bib-grid-relacionados" id="bib-relacionados"></div>
        </div>
    </div>

    {{-- ─────────────── PARTIDAS ─────────────── --}}
    <div class="bib-vista" data-vista="partidas" style="display: none;">
        <p class="bib-descripcion">Base de datos de partidas comentadas clasificadas por nivel y apertura.</p>
        <div class="bib-filtros">
            <div class="bib-buscador">
                <i class="ri-search-line"></i>
                <input type="text" id="bib-partidas-buscar" placeholder="Buscar por jugador, apertura o ECO..." autocomplete="off">
            </div>
            <select id="bib-partidas-nivel" class="bib-select"><option value="">Todos los niveles</option></select>
        </div>
        <div class="bib-tabla-wrap">
            <table class="bib-tabla">
                <thead>
                    <tr>
                        <th>Blancas</th><th>Negras</th><th>Resultado</th><th>Apertura (ECO)</th>
                        <th>Nivel</th><th>Fecha</th><th class="bib-derecha">Acción</th>
                    </tr>
                </thead>
                <tbody id="bib-partidas-tbody"></tbody>
            </table>
        </div>
    </div>

    {{-- ─────────────── VISOR DE PARTIDA ─────────────── --}}
    <div class="bib-vista" data-vista="visor" style="display: none;">
        <a href="#" class="bib-volver" data-ir="partidas"><i class="ri-arrow-left-line"></i> Volver a partidas</a>

        <div class="bib-visor">
            <div class="bib-visor-izq">
                <div class="bib-tablero-marco">
                    <div class="bib-rangos"><span>8</span><span>7</span><span>6</span><span>5</span><span>4</span><span>3</span><span>2</span><span>1</span></div>
                    <div class="bib-tablero" id="bib-tablero"></div>
                    <div></div>
                    <div class="bib-columnas"><span>a</span><span>b</span><span>c</span><span>d</span><span>e</span><span>f</span><span>g</span><span>h</span></div>
                </div>
                <div class="bib-visor-controles">
                    <button class="bib-btn-outline bib-btn-sm" id="bib-visor-inicio" title="Inicio"><i class="ri-skip-back-line"></i></button>
                    <button class="bib-btn-outline bib-btn-sm" id="bib-visor-ant" title="Anterior"><i class="ri-arrow-left-s-line"></i></button>
                    <span class="bib-visor-pos" id="bib-visor-pos">Inicio</span>
                    <button class="bib-btn-outline bib-btn-sm" id="bib-visor-sig" title="Siguiente"><i class="ri-arrow-right-s-line"></i></button>
                    <button class="bib-btn-outline bib-btn-sm" id="bib-visor-fin" title="Final"><i class="ri-skip-forward-line"></i></button>
                </div>
            </div>

            <div class="bib-visor-der">
                <div class="bib-panel">
                    <h2 class="bib-visor-titulo" id="bib-visor-titulo"></h2>
                    <table class="bib-tabla-meta bib-tabla-meta-corta"><tbody id="bib-visor-ficha"></tbody></table>
                    <div class="bib-visor-nivel" id="bib-visor-nivel"></div>
                </div>
                <div class="bib-panel">
                    <h3 class="bib-etiqueta-panel">Jugadas</h3>
                    <div class="bib-jugadas" id="bib-visor-jugadas"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─────────────── MIS GUARDADOS ─────────────── --}}
    <div class="bib-vista" data-vista="guardados" style="display: none;">
        <p class="bib-descripcion">Documentos que has marcado para consultar más tarde.</p>
        <div class="bib-panel bib-vacio" id="bib-guardados-vacio">
            <i class="ri-bookmark-line"></i>
            <p>No tienes documentos guardados.</p>
            <button class="bib-btn-primario" data-ir="catalogo">Explorar catálogo</button>
        </div>
        <div class="bib-lista" id="bib-guardados-lista"></div>
    </div>

    {{-- ─────────────── HISTORIAL ─────────────── --}}
    <div class="bib-vista" data-vista="historial" style="display: none;">
        <div class="bib-fila-cabecera">
            <p class="bib-descripcion">Documentos que has consultado recientemente.</p>
            <button class="bib-btn-peligro bib-btn-sm" id="bib-historial-limpiar"><i class="ri-delete-bin-line"></i> Limpiar historial</button>
        </div>
        <div class="bib-panel bib-vacio" id="bib-historial-vacio">
            <i class="ri-time-line"></i>
            <p>Tu historial está vacío.</p>
            <button class="bib-btn-primario" data-ir="catalogo">Explorar catálogo</button>
        </div>
        <div class="bib-lista" id="bib-historial-lista"></div>
    </div>

    {{-- ─────────────── GESTIÓN DE MATERIAL ─────────────── --}}
    <div class="bib-vista" data-vista="gestion" style="display: none;">
        <div class="bib-fila-cabecera">
            <p class="bib-descripcion">Administra los documentos y recursos de la biblioteca.</p>
            <button class="btn-primary" id="bib-btn-agregar"><i class="ri-add-line"></i> Agregar material</button>
        </div>
        <div class="bib-filtros">
            <div class="bib-buscador bib-buscador-corto">
                <i class="ri-search-line"></i>
                <input type="text" id="bib-gestion-buscar" placeholder="Buscar por título o creador..." autocomplete="off">
            </div>
        </div>
        <div class="bib-tabla-wrap">
            <table class="bib-tabla">
                <thead>
                    <tr>
                        <th>Título</th><th>Tipo</th><th>Nivel</th><th>Formato</th><th>Tamaño</th>
                        <th>Fecha subida</th><th>Estatus</th><th class="bib-derecha">Acciones</th>
                    </tr>
                </thead>
                <tbody id="bib-gestion-tbody"></tbody>
            </table>
        </div>
    </div>
</section>

{{-- ─────────────── MODAL: AGREGAR / EDITAR MATERIAL ─────────────── --}}
<div class="bib-modal-overlay" id="bib-modal">
    <div class="bib-modal">
        <div class="bib-modal-header">
            <h2 id="bib-modal-titulo">Agregar material</h2>
            <button type="button" class="bib-icono-btn" id="bib-modal-cerrar" title="Cerrar"><i class="ri-close-line"></i></button>
        </div>
        <form id="bib-form" autocomplete="off">
            <div class="bib-form-grid">
                <label class="bib-campo bib-campo-ancho">
                    <span>Título *</span>
                    <input type="text" name="titulo" placeholder="Título del material">
                </label>
                <label class="bib-campo">
                    <span>Creador</span>
                    <input type="text" name="creador" placeholder="Autor o institución">
                </label>
                <label class="bib-campo">
                    <span>Tema</span>
                    <input type="text" name="tema" placeholder="Ej. Aperturas, Táctica...">
                </label>
                <label class="bib-campo bib-campo-ancho">
                    <span>Descripción</span>
                    <textarea name="descripcion" rows="3" placeholder="Descripción breve del contenido"></textarea>
                </label>
                <label class="bib-campo">
                    <span>Tipo *</span>
                    <select name="tipo" id="bib-form-tipo"><option value="">Seleccionar tipo</option></select>
                </label>
                <label class="bib-campo">
                    <span>Nivel *</span>
                    <select name="nivel" id="bib-form-nivel"><option value="">Seleccionar nivel</option></select>
                </label>
                <label class="bib-campo bib-campo-ancho">
                    <span>Palabras clave</span>
                    <input type="text" name="palabrasClave" placeholder="Separadas por coma: táctica, apertura, final...">
                </label>

                <div class="bib-campo bib-campo-ancho" id="bib-bloque-archivo">
                    <span>Archivo (PDF, imagen o PGN)</span>
                    <div class="bib-zona-archivo" id="bib-zona-archivo"></div>
                    <input type="file" id="bib-form-archivo" accept=".pdf,.pgn,.png,.jpg,.jpeg" hidden>
                </div>

                <label class="bib-campo bib-campo-ancho" id="bib-bloque-video" style="display: none;">
                    <span>Enlace del video</span>
                    <input type="url" name="enlaceVideo" placeholder="https://...">
                </label>

                <label class="bib-campo bib-campo-ancho" id="bib-bloque-sha" style="display: none;">
                    <span class="bib-texto-suave">Hash SHA-256 (solo lectura)</span>
                    <input type="text" name="sha256" readonly class="bib-sha">
                </label>
            </div>
            <div class="bib-modal-footer">
                <button type="button" class="bib-btn-outline" id="bib-modal-cancelar">Cancelar</button>
                <button type="submit" class="bib-btn-primario" id="bib-modal-guardar" disabled>Guardar material</button>
            </div>
        </form>
    </div>
</div>
