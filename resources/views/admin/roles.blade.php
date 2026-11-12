{{-- ===================== SECCIÓN: ROLES Y PERMISOS ===================== --}}
<section class="section-content" id="section-roles" style="display: none;">

    <div class="section-header">
        <h1 class="section-title">Gestión de Roles</h1>
        @if($permisos->roles_edit ?? false)
            <button class="btn-primary" id="btn-agregar-rol">
                <i class="ri-add-line"></i> Agregar Rol
            </button>
        @endif
    </div>

    <div class="card">

        {{-- Controles --}}
        <div class="controls-container">
            <div class="search-bar">
                <i class="ri-search-line search-icon"></i>
                <input type="text" id="buscador-roles" placeholder="Buscar roles por nombre o ID..." autocomplete="off">
            </div>
            <div class="filters-row">
                <div class="select-wrapper custom-dropdown" id="dropdown-status-roles">
                    <div class="custom-select-trigger">
                        <span class="selected-text" data-value="">Todos los estatus</span>
                        <i class="ri-arrow-down-s-line"></i>
                    </div>
                    <div class="custom-options-container">
                        <div class="custom-option selected" data-value="">Todos los estatus</div>
                        <div class="custom-option" data-value="activo">Activo</div>
                        <div class="custom-option" data-value="inactivo">Inactivo</div>
                    </div>
                </div>
                <button id="btn-limpiar-roles" class="btn-clear-filters">Limpiar filtros</button>
            </div>
        </div>

        {{-- Tabla --}}
        <div class="table-wrapper">
            <table class="data-table" id="tabla-roles">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Estatus</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-roles">
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2rem; color:var(--texto-suave);">
                            <i class="ri-loader-4-line ri-spin" style="font-size:1.5rem;"></i>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div class="pagination">
            <div class="pagination-info" id="roles-pag-info">—</div>
            <div class="pagination-btns" id="roles-pag-btns"></div>
        </div>

    </div>
</section>

{{-- ===================== MODAL: VER ROL ===================== --}}
<div class="modal-overlay" id="modal-ver-rol">
    <div class="modal-box modal-box-large">
        <div class="modal-header">
            <div class="modal-title-group">
                <i class="ri-eye-line modal-title-icon"></i>
                <h2 class="modal-title">Detalle del Rol</h2>
            </div>
            <button class="modal-close-btn" id="modal-close-ver-rol" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="ver-rol-loader" style="text-align:center;padding:2rem;">
                <i class="ri-loader-4-line ri-spin" style="font-size:2rem;color:var(--naranja)"></i>
            </div>
            <div id="ver-rol-content" style="display:none;">
                <div class="modal-section-label"><i class="ri-information-line"></i> Detalles del Rol</div>
                <div class="modal-grid" style="margin-bottom:1.2rem;">
                    <div class="form-group-modal">
                        <label>Nombre</label>
                        <p id="vr-nombre" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Tipo de Acceso</label>
                        <p id="vr-tipo" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Estatus</label>
                        <p id="vr-estatus" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label>Descripción</label>
                        <p id="vr-descripcion" class="detalle-valor">—</p>
                    </div>
                </div>

                <div class="modal-section-label"><i class="ri-lock-password-line"></i> Matriz de Permisos</div>
                <div class="permissions-matrix-wrapper">
                    <table class="permissions-table">
                        <thead>
                            <tr>
                                <th>Módulo</th>
                                <th class="text-center">Ver</th>
                                <th class="text-center">Editar</th>
                                <th class="text-center">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody id="vr-permisos-tbody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" id="btn-cerrar-ver-rol">Cerrar</button>
        </div>
    </div>
</div>

{{-- ===================== MODAL: AGREGAR / EDITAR ROL ===================== --}}
<div class="modal-overlay" id="modal-agregar-rol">
    <div class="modal-box modal-box-large">
        <div class="modal-header">
            <div class="modal-title-group">
                <i class="ri-shield-keyhole-line modal-title-icon"></i>
                <h2 class="modal-title" id="modal-rol-titulo">Agregar Nuevo Rol</h2>
            </div>
            <button class="modal-close-btn" id="modal-close-rol" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="form-agregar-rol" novalidate>
                <input type="hidden" id="rol-edit-id" value="">

                {{-- Detalles del Rol --}}
                <div class="modal-section-label">
                    <i class="ri-information-line"></i> Detalles del Rol
                </div>
                <div class="modal-grid">
                    <div class="form-group-modal modal-col-full">
                        <label for="ro-nombre">Nombre del Rol <span style="color:var(--naranja)">*</span></label>
                        <input type="text" id="ro-nombre" name="ro_nombre" placeholder="Ej. Editor Académico" required>
                        <span class="error-msg-modal" id="err-ro-nombre"></span>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="ro-descripcion">Descripción <span style="color:var(--naranja)">*</span></label>
                        <textarea id="ro-descripcion" name="ro_descripcion" placeholder="Explica qué funciones tendrá este rol..." rows="2"
                            style="width:100%;padding:10px;border:2px solid var(--borde);border-radius:10px;font-family:'Inter',sans-serif;resize:vertical;background:#fafafa;"></textarea>
                        <span class="error-msg-modal" id="err-ro-descripcion"></span>
                    </div>

                    @if($administrativo)
                    <div class="form-group-modal">
                        <label for="ro-tipo">Tipo de Acceso <span style="color:var(--naranja)">*</span></label>
                        <div class="form-dropdown" id="dropdown-ro-tipo" tabindex="0">
                            <div class="form-select-trigger">
                                <span class="selected-text" data-value="false">Estándar</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                <div class="form-option selected" data-value="false">Estándar</div>
                                <div class="form-option" data-value="true">Administrador</div>
                            </div>
                            <input type="hidden" id="ro-tipo" name="administrativo" value="false">
                        </div>
                    </div>
                    @endif

                    <div class="form-group-modal">
                        <label for="ro-estatus">Estatus <span style="color:var(--naranja)">*</span></label>
                        <div class="form-dropdown" id="dropdown-ro-estatus" tabindex="0">
                            <div class="form-select-trigger">
                                <span class="selected-text" data-value="true">Activo</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                <div class="form-option selected" data-value="true">Activo</div>
                                <div class="form-option" data-value="false">Inactivo</div>
                            </div>
                            <input type="hidden" id="ro-estatus" name="ro_estatus" value="true">
                        </div>
                    </div>
                </div>

                {{-- Matriz de Permisos --}}
                <div class="modal-section-label" style="margin-top:20px;">
                    <i class="ri-lock-password-line"></i> Matriz de Permisos
                </div>
                <div class="permissions-matrix-wrapper">
                    <table class="permissions-table">
                        <thead>
                            <tr>
                                <th>Módulo</th>
                                <th class="text-center">Ver (Acceso)</th>
                                <th class="text-center">Crear/Editar (Escritura)</th>
                                <th class="text-center">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $modulos = [
                                    ['id' => 'alumno',           'nombre' => 'Alumnos',          'has_edit' => true],
                                    ['id' => 'profesor',         'nombre' => 'Profesores',        'has_edit' => true],
                                    ['id' => 'personal',         'nombre' => 'Personal',          'has_edit' => true],
                                    ['id' => 'roles',            'nombre' => 'Roles',             'has_edit' => true],
                                    ['id' => 'sedes',            'nombre' => 'Sedes',             'has_edit' => true],
                                    ['id' => 'grupos',           'nombre' => 'Grupos',            'has_edit' => true],
                                    ['id' => 'extracurriculares','nombre' => 'Extraescolares',    'has_edit' => true],
                                    ['id' => 'estatus',          'nombre' => 'Status',            'has_edit' => false],
                                    ['id' => 'pagos',            'nombre' => 'Pagos',             'has_edit' => true],
                                    ['id' => 'niveles',          'nombre' => 'Niveles',           'has_edit' => true],
                                    ['id' => 'biblioteca',       'nombre' => 'Biblioteca',        'has_edit' => true, 'has_delete' => true],
                                ];
                            @endphp
                            @foreach($modulos as $mod)
                                <tr>
                                    <td><strong>{{ $mod['nombre'] }}</strong></td>
                                    <td class="text-center">
                                        <label class="custom-checkbox-container">
                                            <input type="checkbox" name="{{ $mod['id'] }}_ver" id="perm-{{ $mod['id'] }}-ver" value="1">
                                            <span class="checkmark"></span>
                                        </label>
                                    </td>
                                    <td class="text-center">
                                        @if($mod['has_edit'])
                                            <label class="custom-checkbox-container">
                                                <input type="checkbox" name="{{ $mod['id'] }}_edit" id="perm-{{ $mod['id'] }}-edit" value="1">
                                                <span class="checkmark"></span>
                                            </label>
                                        @else
                                            <span style="color:var(--texto-suave);font-size:0.8rem;">N/A</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($mod['has_delete'] ?? false)
                                            <label class="custom-checkbox-container">
                                                <input type="checkbox" name="{{ $mod['id'] }}_eliminar" id="perm-{{ $mod['id'] }}-eliminar" value="1">
                                                <span class="checkmark"></span>
                                            </label>
                                        @else
                                            <span style="color:var(--texto-suave);font-size:0.8rem;" title="Eliminar = Crear/Editar con rol administrativo">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Footer --}}
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" id="btn-cancelar-modal-rol">Cancelar</button>
                    <button type="submit" class="btn-modal-submit" id="btn-submit-rol">
                        <i class="ri-save-line"></i> <span id="btn-rol-label">Guardar Rol</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
