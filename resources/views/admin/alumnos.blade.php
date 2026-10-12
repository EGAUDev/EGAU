<!-- ===================== SECCIÓN: ALUMNOS ===================== -->
<section class="section-content" id="section-alumnos">

    <!-- Cabecera de sección -->
    <div class="section-header">
        <h1 class="section-title">Gestión de Alumnos</h1>
        @if($permisos->alumno_edit ?? false)
            <button class="btn-primary" id="btn-agregar">
                <i class="ri-add-line"></i> Agregar Alumno
            </button>
        @endif  
    </div>

    <!-- Tarjeta de tabla -->
    <div class="card">

        <!-- Cabecera de controles: Búsqueda y Filtros -->
        <div class="controls-container">
            <!-- Buscador -->
            <div class="search-bar">
                <i class="ri-search-line search-icon"></i>
                <input type="text" id="buscador" placeholder="Buscar alumno..." autocomplete="off">
            </div>

            <!-- Filtros -->
            <div class="filters-row">
                <!-- Nivel -->
                <div class="select-wrapper custom-dropdown" id="dropdown-nivel">
                    <div class="custom-select-trigger">
                        <span class="selected-text" data-value="">Todos los niveles</span>
                        <i class="ri-arrow-down-s-line"></i>
                    </div>
                    <div class="custom-options-container">
                        <div class="custom-option selected" data-value="">Todos los niveles</div>
                        <div class="custom-option" data-value="peon">♟ Peón (0–149)</div>
                        <div class="custom-option" data-value="caballo">♞ Caballo (150–399)</div>
                        <div class="custom-option" data-value="alfil">♝ Alfil (400–799)</div>
                        <div class="custom-option" data-value="torre">♜ Torre (800–1499)</div>
                        <div class="custom-option" data-value="reina">♛ Reina (1500–2999)</div>
                        <div class="custom-option" data-value="rey">♚ Rey (3000+)</div>
                    </div>
                </div>

                <!-- Sede -->
                <div class="select-wrapper custom-dropdown" id="dropdown-sede">
                    <div class="custom-select-trigger">
                        <span class="selected-text" data-value="">Todas las sedes</span>
                        <i class="ri-arrow-down-s-line"></i>
                    </div>
                    <div class="custom-options-container">
                        <div class="custom-option selected" data-value="">Todas las sedes</div>
                        @foreach($sedes as $s)
                            <div class="custom-option" data-value="{{ $s->id_sede }}">{{ $s->nombre }}</div>
                        @endforeach
                    </div>
                </div>

                <!-- Status -->
                <div class="select-wrapper custom-dropdown" id="dropdown-status">
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

                <button id="btn-limpiar" class="btn-clear-filters">
                    Limpiar filtros
                </button>
            </div>
        </div>

        <!-- Tabla -->
        <div class="table-wrapper">
            <table class="data-table" id="tabla-alumnos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Edad</th>
                        <th>Nivel</th>
                        <th>Sede</th>
                        <th>Status</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="pagination">
            <div class="pagination-btns">
                <button class="pag-btn" disabled><i class="ri-arrow-left-s-line"></i></button>
                <button class="pag-btn active">1</button>
                <button class="pag-btn"><i class="ri-arrow-right-s-line"></i></button>
            </div>
        </div>

    </div><!-- /card -->
</section>

<!-- ===================== MODAL: AGREGAR ALUMNO ===================== -->
<div class="modal-overlay" id="modal-agregar-alumno">
    <div class="modal-box">

        <!-- Cabecera del modal -->
        <div class="modal-header">
            <div class="modal-title-group">
                <i class="ri-user-add-line modal-title-icon"></i>
                <h2 class="modal-title">Agregar Alumno</h2>
            </div>
            <button class="modal-close-btn" id="modal-close-alumno" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <!-- Cuerpo del modal con scroll -->
        <div class="modal-body">
            <form id="form-agregar-alumno" novalidate>

                <!-- ===== SECCIÓN: Datos del Alumno ===== -->
                <div class="modal-section-label">
                    <i class="ri-graduation-cap-line"></i> Datos del Alumno
                </div>

                <div class="modal-grid">
                    <div class="form-group-modal">
                        <label for="al-nombre">Nombre</label>
                        <input type="text" id="al-nombre" name="al_nombre" placeholder="Nombre(s)" required>
                        <span class="error-msg-modal" id="err-al-nombre"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="al-ap-paterno">Apellido Paterno</label>
                        <input type="text" id="al-ap-paterno" name="al_ap_paterno" placeholder="Apellido paterno"
                            required>
                        <span class="error-msg-modal" id="err-al-ap-paterno"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="al-ap-materno">Apellido Materno</label>
                        <input type="text" id="al-ap-materno" name="al_ap_materno" placeholder="Apellido materno">
                    </div>
                    <div class="form-group-modal">
                        <label for="al-fecha-nacimiento">Fecha de Nacimiento <span
                                style="color:var(--naranja)">*</span></label>
                        <input type="date" id="al-fecha-nacimiento" name="al_fecha_nacimiento" required>
                        <span class="error-msg-modal" id="err-al-fecha-nacimiento"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="al-telefono">Número de Teléfono</label>
                        <input type="tel" id="al-telefono" name="al_telefono" placeholder="10 dígitos" maxlength="10">
                        <span class="error-msg-modal" id="err-al-telefono"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="al-genero">Género</label>
                        <div class="form-dropdown" id="dropdown-al-genero" tabindex="0">
                            <div class="form-select-trigger" id="trigger-al-genero">
                                <span class="selected-text" data-value="">Selecciona una opción</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                <div class="form-option" data-value="f">Femenino (F)</div>
                                <div class="form-option" data-value="m">Masculino (M)</div>
                                <div class="form-option" data-value="o">Otro (O)</div>
                            </div>
                            <input type="hidden" id="al-genero" name="al_genero" value="">
                        </div>
                        <span class="error-msg-modal" id="err-al-genero"></span>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="al-sede">Sede <span style="color:var(--naranja)">*</span></label>
                        <div class="form-dropdown" id="dropdown-al-sede" tabindex="0">
                            <div class="form-select-trigger">
                                <span class="selected-text" data-value="">Selecciona una sede</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                @foreach($sedes as $s)
                                    <div class="form-option" data-value="{{ $s->id_sede }}">{{ $s->nombre }}</div>
                                @endforeach
                            </div>
                            <input type="hidden" id="al-sede" name="al_sede" value="">
                        </div>
                        <span class="error-msg-modal" id="err-al-sede"></span>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="al-puntos-inicial">Puntaje Inicial <span
                                style="color:var(--naranja)">*</span></label>
                        <input type="number" id="al-puntos-inicial" name="al_puntos_inicial" placeholder="Ej. 500"
                            min="0" max="2000" value="0" required>
                        <span class="error-msg-modal" id="err-al-puntos-inicial"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="al-estado">Estado <span style="color:var(--naranja)">*</span></label>
                        @include('admin.partials.dropdown-estados', ['prefix' => 'al'])
                        <span class="error-msg-modal" id="err-al-estado"></span>
                    </div>
                    <div class="form-group-modal">
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 2;">
                                <label for="al-ciudad">Ciudad <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="al-ciudad" name="al_ciudad" placeholder="Ciudad" required>
                                <span class="error-msg-modal" id="err-al-ciudad"></span>
                            </div>
                            <div style="flex: 1;">
                                <label for="al-cp">CP <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="al-cp" name="al_cp" placeholder="CP" maxlength="5" required>
                                <span class="error-msg-modal" id="err-al-cp"></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 2;">
                                <label for="al-calle">Calle <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="al-calle" name="al_calle" placeholder="Nombre de calle" required>
                                <span class="error-msg-modal" id="err-al-calle"></span>
                            </div>
                            <div style="flex: 2;">
                                <label for="al-colonia">Colonia <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="al-colonia" name="al_colonia" placeholder="Colonia" required>
                                <span class="error-msg-modal" id="err-al-colonia"></span>
                            </div>
                            <div style="flex: 1;">
                                <label for="al-numero">Número <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="al-numero" name="al_numero" placeholder="# Ext/Int" required>
                                <span class="error-msg-modal" id="err-al-numero"></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="al-correo">Correo Electrónico</label>
                        <input type="email" id="al-correo" name="al_correo" placeholder="correo@ejemplo.com" required>
                        <span class="error-msg-modal" id="err-al-correo"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="al-password">Contraseña</label>
                        <div class="input-password-wrapper">
                            <input type="password" id="al-password" name="al_password" placeholder="Mínimo 6 caracteres"
                                required minlength="6">
                            <button type="button" class="toggle-password" data-target="al-password"
                                title="Mostrar/Ocultar">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                        <span class="error-msg-modal" id="err-al-password"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="al-password-confirm">Confirmar Contraseña</label>
                        <div class="input-password-wrapper">
                            <input type="password" id="al-password-confirm" name="al_password_confirm"
                                placeholder="Repite la contraseña" required>
                            <button type="button" class="toggle-password" data-target="al-password-confirm"
                                title="Mostrar/Ocultar">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                        <span class="error-msg-modal" id="err-al-password-confirm"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="al-estatus">Estatus</label>
                        <div class="form-dropdown" id="dropdown-al-estatus" tabindex="0">
                            <div class="form-select-trigger">
                                <span class="selected-text" data-value="1">Activo</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                <div class="form-option selected" data-value="1">Activo</div>
                                <div class="form-option" data-value="0">Inactivo</div>
                            </div>
                            <input type="hidden" id="al-estatus" name="al_estatus" value="1">
                        </div>
                    </div>
                </div>

                <!-- ===== DIVISOR ===== -->
                <div class="modal-divider">
                    <span id="label-divisor-tutor">Datos del Tutor (Requerido para menores de edad)</span>
                </div>

                <!-- ===== SECCIÓN: Datos del Tutor ===== -->
                <div class="modal-section-label">
                    <i class="ri-parent-line"></i> Información del Tutor
                </div>

                <div class="modal-grid">
                    <div class="form-group-modal">
                        <label for="tu-nombre">Nombre</label>
                        <input type="text" id="tu-nombre" name="tu_nombre" placeholder="Nombre(s)">
                        <span class="error-msg-modal" id="err-tu-nombre"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="tu-ap-paterno">Apellido Paterno</label>
                        <input type="text" id="tu-ap-paterno" name="tu_ap_paterno" placeholder="Apellido paterno">
                        <span class="error-msg-modal" id="err-tu-ap-paterno"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="tu-ap-materno">Apellido Materno</label>
                        <input type="text" id="tu-ap-materno" name="tu_ap_materno" placeholder="Apellido materno">
                    </div>
                    <div class="form-group-modal">
                        <label for="tu-telefono">Número de Teléfono</label>
                        <input type="tel" id="tu-telefono" name="tu_telefono" placeholder="10 dígitos" maxlength="10">
                        <span class="error-msg-modal" id="err-tu-telefono"></span>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="tu-parentesco">Parentesco</label>
                        <div class="form-dropdown" id="dropdown-tu-parentesco" tabindex="0">
                            <div class="form-select-trigger">
                                <span class="selected-text" data-value="">Selecciona el parentesco</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                <div class="form-option" data-value="padre">Padre</div>
                                <div class="form-option" data-value="madre">Madre</div>
                                <div class="form-option" data-value="abuelo_a">Abuelo/a</div>
                                <div class="form-option" data-value="tutor_legal">Tutor Legal</div>
                                <div class="form-option" data-value="familiar">Familiar</div>
                                <div class="form-option" data-value="otro">Otro</div>
                            </div>
                            <input type="hidden" id="tu-parentesco" name="tu_parentesco" value="">
                        </div>
                        <span class="error-msg-modal" id="err-tu-parentesco"></span>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="tu-correo">Correo Electrónico</label>
                        <input type="email" id="tu-correo" name="tu_correo" placeholder="correo@ejemplo.com">
                        <span class="error-msg-modal" id="err-tu-correo"></span>
                    </div>
                </div>

                <!-- ===== PIE DEL FORMULARIO ===== -->
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" id="btn-cancelar-modal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="ri-save-line"></i> Guardar Alumno
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
</div>

<!-- ===================== MODAL: VER DETALLE ALUMNO ===================== -->
<div class="modal-overlay" id="modal-ver-alumno">
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-title-group">
                <i class="ri-eye-line modal-title-icon"></i>
                <h2 class="modal-title">Detalle del Alumno</h2>
            </div>
            <button class="modal-close-btn" id="modal-close-ver-alumno" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <div class="modal-body">

            <!-- Loader -->
            <div id="ver-alumno-loader" style="text-align:center; padding:2rem;">
                <i class="ri-loader-4-line ri-spin" style="font-size:2rem; color:var(--naranja)"></i>
                <p style="margin-top:.5rem; color:var(--texto-secundario)">Cargando datos...</p>
            </div>

            <!-- Contenido (se llena desde JS) -->
            <div id="ver-alumno-content" style="display:none;">

                <div class="modal-section-label"><i class="ri-user-line"></i> Datos Personales</div>
                <div class="modal-grid" style="margin-bottom:1.2rem;">
                    <div class="form-group-modal">
                        <label>Nombre completo</label>
                        <p id="ver-nombre" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Fecha de Nacimiento</label>
                        <p id="ver-fecha-nac" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Edad</label>
                        <p id="ver-edad" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Género</label>
                        <p id="ver-genero" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Teléfono</label>
                        <p id="ver-telefono" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Correo</label>
                        <p id="ver-correo" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Estatus</label>
                        <p id="ver-estatus" class="detalle-valor">—</p>
                    </div>
                </div>

                <div class="modal-section-label"><i class="ri-graduation-cap-line"></i> Datos Escolares</div>
                <div class="modal-grid" style="margin-bottom:1.2rem;">
                    <div class="form-group-modal">
                        <label>Sede</label>
                        <p id="ver-sede" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Nivel</label>
                        <p id="ver-nivel" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Puntos</label>
                        <p id="ver-puntos" class="detalle-valor">—</p>
                    </div>
                </div>

                <div class="modal-section-label"><i class="ri-map-pin-line"></i> Dirección</div>
                <div class="modal-grid" style="margin-bottom:1.2rem;">
                    <div class="form-group-modal">
                        <label>Estado</label>
                        <p id="ver-estado" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Ciudad</label>
                        <p id="ver-ciudad" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Calle</label>
                        <p id="ver-calle" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Colonia</label>
                        <p id="ver-colonia" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Número</label>
                        <p id="ver-numero" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Código Postal</label>
                        <p id="ver-cp" class="detalle-valor">—</p>
                    </div>
                </div>

                <div id="ver-tutor-section" style="display:none;">
                    <div class="modal-section-label"><i class="ri-parent-line"></i> Información del Tutor</div>
                    <div class="modal-grid">
                        <div class="form-group-modal">
                            <label>Nombre del Tutor</label>
                            <p id="ver-tu-nombre" class="detalle-valor">—</p>
                        </div>
                        <div class="form-group-modal">
                            <label>Parentesco</label>
                            <p id="ver-tu-parentesco" class="detalle-valor">—</p>
                        </div>
                        <div class="form-group-modal">
                            <label>Teléfono Tutor</label>
                            <p id="ver-tu-telefono" class="detalle-valor">—</p>
                        </div>
                        <div class="form-group-modal">
                            <label>Correo Tutor</label>
                            <p id="ver-tu-correo" class="detalle-valor">—</p>
                        </div>
                    </div>
                </div>

            </div><!-- /ver-alumno-content -->
        </div><!-- /modal-body -->

        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" id="btn-cerrar-ver-alumno">Cerrar</button>
        </div>

    </div>
</div>

<style>
    .detalle-valor {
        margin: 0;
        padding: 0.45rem 0.6rem;
        background: var(--fondo-hover, rgba(0, 0, 0, .05));
        border-radius: 6px;
        font-size: 0.92rem;
        color: var(--texto-principal, #1a1a2e);
        min-height: 2rem;
        word-break: break-word;
    }
</style>