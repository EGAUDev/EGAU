{{-- ===================== SECCIÓN: BIBLIOTECA VIRTUAL (SCRUM-18) ===================== --}}
{{-- Visible con el permiso biblioteca.ver; la gestión pide biblioteca.editar (SCRUM-46). --}}
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
        <p class="bib-descripcion">Partidas comentadas y las aperturas ECO de Lichess, con visor de jugadas.</p>
        <div class="bib-filtros">
            <div class="bib-buscador">
                <i class="ri-search-line"></i>
                <input type="text" id="bib-partidas-buscar" placeholder="Buscar por jugador, apertura o ECO..." autocomplete="off">
            </div>
            <select id="bib-partidas-tipo" class="bib-select">
                <option value="partida">Partidas comentadas</option>
                <option value="apertura">Aperturas (ECO)</option>
            </select>
            <select id="bib-partidas-nivel" class="bib-select"><option value="">Todos los niveles</option></select>
        </div>
        <p class="bib-conteo" id="bib-partidas-conteo"></p>
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
        <div class="bib-paginacion" id="bib-partidas-paginacion"></div>
    </div>

    {{-- ─────────────── VISOR DE PARTIDA ─────────────── --}}
    <div class="bib-vista" data-vista="visor" style="display: none;">
        <a href="#" class="bib-volver" data-ir="partidas"><i class="ri-arrow-left-line"></i> Volver a partidas</a>

        <div class="bib-panel bib-visor-cabecera">
            <div>
                <h2 class="bib-visor-titulo" id="bib-visor-titulo"></h2>
                <div class="bib-visor-nivel" id="bib-visor-nivel"></div>
            </div>
            <table class="bib-tabla-meta bib-tabla-meta-corta"><tbody id="bib-visor-ficha"></tbody></table>
        </div>
        {{-- Tablero, jugadas con variantes y exportar PGN: comun/visorPartida.js --}}
        <div class="bib-panel" id="bib-visor-ev"></div>
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
        <div class="bib-paginacion" id="bib-gestion-paginacion"></div>
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
                    <span>Nivel mínimo</span>
                    <select name="nivel" id="bib-form-nivel"><option value="">Seleccionar nivel</option></select>
                </label>
                <label class="bib-campo bib-campo-ancho">
                    <span>Palabras clave</span>
                    <input type="text" name="palabrasClave" placeholder="Separadas por coma: táctica, apertura, final...">
                </label>

                <div class="bib-campo bib-campo-ancho" id="bib-bloque-archivo">
                    <span>Archivo (PDF, EPUB, MP4, WEBM o PGN)</span>
                    <div class="bib-zona-archivo" id="bib-zona-archivo"></div>
                    <input type="file" id="bib-form-archivo" accept=".pdf,.epub,.mp4,.webm,.pgn" hidden>
                </div>

                <label class="bib-campo bib-campo-ancho" id="bib-bloque-pgn" style="display: none;">
                    <span>PGN de la partida (se revisa que todas las jugadas sean legales)</span>
                    <textarea name="pgn" rows="6" class="bib-sha" placeholder='[White "Morphy"]&#10;1. e4 e5 2. Cf3 d6 ...  (inglés o español)'></textarea>
                    <small id="bib-pgn-estado" class="bib-texto-suave"></small>
                </label>

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
