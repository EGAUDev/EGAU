<!-- ======================= SECCIÓN PROFESORES ======================= -->
<section class="section-content" id="section-profesores" style="display: none;">

    <div class="section-header">
        <h1 class="section-title">Profesores</h1>
       @if($permisos->profesor_edit ?? false)
     <button class="btn-primary" id="btn-agregar-profesor">
        <i class="ri-add-line"></i> Agregar Profesor
    </button>
        @endif
    </div>

    <div class="search-bar" style="margin-bottom: 24px;">
        <i class="ri-search-line search-icon"></i>
        <input type="text" id="buscador-profesores"
            placeholder="Buscar profesor por nombre, especialidad o email..." autocomplete="off">
    </div>

    <div class="profesores-grid" id="grid-profesores">
        @foreach($profesores as $pr)
        <div class="profesor-card" data-nombre="{{ $pr->nombre }} {{ $pr->apellido_p }}" data-email="{{ $pr->email }}">
            <div class="profesor-card-body">
                <h3 class="profesor-nombre">{{ $pr->nombre }} {{ $pr->apellido_p }}</h3>
                <div class="profesor-info">
                    <span><i class="ri-mail-line"></i> {{ $pr->email }}</span>
                    <span><i class="ri-phone-line"></i> {{ $pr->telefono ?? 'N/A' }}</span>
                </div>
            </div>
            <div class="profesor-card-footer">
                <button class="btn-profesor-ver" title="Ver" data-id="{{ $pr->id_profesor }}" data-info="{{ json_encode($pr) }}"><i class="ri-eye-line"></i> Ver</button>
                <button class="btn-profesor-editar" data-id="{{ $pr->id_profesor }}" data-info="{{ json_encode($pr) }}"><i class="ri-edit-line"></i> Editar</button>
                <button class="btn-profesor-eliminar" data-id="{{ $pr->id_profesor }}"><i class="ri-delete-bin-line"></i></button>
            </div>
        </div>
        @endforeach
    </div>
    <p class="profesores-empty" id="profesores-empty" style="display:none;">No se encontraron profesores.</p>

</section>

<!-- ======================= MODAL: AGREGAR PROFESOR ======================= -->
<div class="modal-overlay" id="modal-agregar-profesor">
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-title-icon"><i class="ri-user-star-line"></i></span>
                <h2 class="modal-title">Agregar Profesor</h2>
            </div>
            <button type="button" class="modal-close-btn" id="modal-close-profesor" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <div class="modal-body">
            <form id="form-agregar-profesor" novalidate>
                @csrf
                <input type="hidden" name="rol" value="profesor">

                <div class="modal-section-label">
                    <i class="ri-user-line"></i> Información del Profesor
                </div>

                <div class="modal-grid">
                    <div class="form-group-modal">
                        <label for="pr-nombre">Nombre <span style="color:var(--naranja)">*</span></label>
                        <input type="text" id="pr-nombre" name="pr_nombre" placeholder="Nombre(s)" required>
                        <span class="error-msg-modal" id="err-pr-nombre"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pr-ap-paterno">Apellido Paterno <span style="color:var(--naranja)">*</span></label>
                        <input type="text" id="pr-ap-paterno" name="pr_ap_paterno" placeholder="Apellido paterno" required>
                        <span class="error-msg-modal" id="err-pr-ap-paterno"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pr-ap-materno">Apellido Materno</label>
                        <input type="text" id="pr-ap-materno" name="pr_ap_materno" placeholder="Apellido materno">
                    </div>
                    <div class="form-group-modal">
                        <label for="pr-fecha-nacimiento">Fecha de Nacimiento <span style="color:var(--naranja)">*</span></label>
                        <input type="date" id="pr-fecha-nacimiento" name="pr_fecha_nacimiento" required>
                        <span class="error-msg-modal" id="err-pr-fecha-nacimiento"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pr-telefono">Número de Teléfono</label>
                        <input type="tel" id="pr-telefono" name="pr_telefono" placeholder="10 dígitos" maxlength="10">
                        <span class="error-msg-modal" id="err-pr-telefono"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pr-genero">Género</label>
                        <div class="form-dropdown" id="dropdown-pr-genero" tabindex="0">
                            <div class="form-select-trigger" id="trigger-pr-genero">
                                <span class="selected-text" data-value="">Selecciona una opción</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                <div class="form-option" data-value="f">Femenino (F)</div>
                                <div class="form-option" data-value="m">Masculino (M)</div>
                                <div class="form-option" data-value="o">Otro (O)</div>
                            </div>
                            <input type="hidden" id="pr-genero" name="pr_genero" value="">
                        </div>
                        <span class="error-msg-modal" id="err-pr-genero"></span>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="pr-sede">Sede <span style="color:var(--naranja)">*</span></label>
                        <div class="form-dropdown" id="dropdown-pr-sede" tabindex="0">
                            <div class="form-select-trigger">
                                <span class="selected-text" data-value="">Selecciona una sede</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                @foreach($sedes as $s)
                                <div class="form-option" data-value="{{ $s->id_sede }}">{{ $s->nombre }}</div>
                                @endforeach
                            </div>
                            <input type="hidden" id="pr-sede" name="pr_sede" value="">
                        </div>
                        <span class="error-msg-modal" id="err-pr-sede"></span>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="pr-puntos">Puntos <span style="color:var(--naranja)">*</span></label>
                        <input type="number" id="pr-puntos" name="pr_puntos" placeholder="Ej. 100" min="0" max="2000" value="0" required>
                        <span class="error-msg-modal" id="err-pr-puntos"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pr-estado">Estado <span style="color:var(--naranja)">*</span></label>
                        @include('admin.partials.dropdown-estados', ['prefix' => 'pr'])
                        <span class="error-msg-modal" id="err-pr-estado"></span>
                    </div>
                    <div class="form-group-modal">
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 2;">
                                <label for="pr-ciudad">Ciudad <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="pr-ciudad" name="pr_ciudad" placeholder="Ciudad" required>
                                <span class="error-msg-modal" id="err-pr-ciudad"></span>
                            </div>
                            <div style="flex: 1;">
                                <label for="pr-cp">CP <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="pr-cp" name="pr_cp" placeholder="CP" maxlength="5" required>
                                <span class="error-msg-modal" id="err-pr-cp"></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 2;">
                                <label for="pr-calle">Calle <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="pr-calle" name="pr_calle" placeholder="Nombre de la calle" required>
                                <span class="error-msg-modal" id="err-pr-calle"></span>
                            </div>
                            <div style="flex: 2;">
                                <label for="pr-colonia">Colonia <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="pr-colonia" name="pr_colonia" placeholder="Colonia" required>
                                <span class="error-msg-modal" id="err-pr-colonia"></span>
                            </div>
                            <div style="flex: 1;">
                                <label for="pr-numero">Número <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="pr-numero" name="pr_numero" placeholder="# Ext/Int" required>
                                <span class="error-msg-modal" id="err-pr-numero"></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="pr-correo">Correo Electrónico <span style="color:var(--naranja)">*</span></label>
                        <input type="email" id="pr-correo" name="pr_correo" placeholder="correo@ejemplo.com" required>
                        <span class="error-msg-modal" id="err-pr-correo"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pr-password">Contraseña <span style="color:var(--naranja)">*</span></label>
                        <div class="input-password-wrapper">
                            <input type="password" id="pr-password" name="pr_password" placeholder="Mínimo 6 caracteres" required minlength="6">
                            <button type="button" class="toggle-password" data-target="pr-password" title="Mostrar/Ocultar">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                        <span class="error-msg-modal" id="err-pr-password"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pr-password-confirm">Confirmar Contraseña <span style="color:var(--naranja)">*</span></label>
                        <div class="input-password-wrapper">
                            <input type="password" id="pr-password-confirm" name="pr_password_confirm" placeholder="Repite la contraseña" required>
                            <button type="button" class="toggle-password" data-target="pr-password-confirm" title="Mostrar/Ocultar">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                        <span class="error-msg-modal" id="err-pr-password-confirm"></span>
                    </div>

<div class="form-group-modal">
    <label for="pr-estatus">Estatus</label>
    <div class="form-dropdown" id="dropdown-pr-estatus" tabindex="0">
        <div class="form-select-trigger">
            <span class="selected-text" data-value="1">Activo</span>
            <i class="ri-arrow-down-s-line"></i>
        </div>
        <div class="form-options-container">
            <div class="form-option selected" data-value="1">Activo</div>
            <div class="form-option" data-value="0">Inactivo</div>
        </div>
        <input type="hidden" id="pr-estatus" name="pr_estatus" value="1">
    </div>
</div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" id="btn-cancelar-modal-profesor">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="ri-save-line"></i> Guardar Profesor
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- ======================= MODAL: VER DETALLE PROFESOR ======================= -->
<div class="modal-overlay" id="modal-ver-profesor">
    <div class="modal-box">

        <div class="modal-header">
            <div class="modal-title-group">
                <i class="ri-eye-line modal-title-icon"></i>
                <h2 class="modal-title">Detalle del Profesor</h2>
            </div>
            <button class="modal-close-btn" id="modal-close-ver-profesor" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <div class="modal-body">
            <div id="ver-profesor-loader" style="text-align:center; padding:2rem;">
                <i class="ri-loader-4-line ri-spin" style="font-size:2rem; color:var(--naranja)"></i>
                <p style="margin-top:.5rem; color:var(--texto-secundario)">Cargando datos...</p>
            </div>

            <div id="ver-profesor-content" style="display:none;">

                <div class="modal-section-label"><i class="ri-user-line"></i> Datos Personales</div>
                <div class="modal-grid" style="margin-bottom:1.2rem;">
                    <div class="form-group-modal">
                        <label>Nombre completo</label>
                        <p id="vpr-nombre" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Fecha de Nacimiento</label>
                        <p id="vpr-fecha-nac" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Edad</label>
                        <p id="vpr-edad" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Género</label>
                        <p id="vpr-genero" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Teléfono</label>
                        <p id="vpr-telefono" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Correo</label>
                        <p id="vpr-correo" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Estatus</label>
                        <p id="vpr-estatus" class="detalle-valor">—</p>
                    </div>
                </div>

                <div class="modal-section-label"><i class="ri-user-star-line"></i> Datos Escolares</div>
                <div class="modal-grid" style="margin-bottom:1.2rem;">
                    <div class="form-group-modal">
                        <label>Sede</label>
                        <p id="vpr-sede" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Puntaje</label>
                        <p id="vpr-puntos" class="detalle-valor">—</p>
                    </div>
                </div>

                <div class="modal-section-label"><i class="ri-map-pin-line"></i> Dirección</div>
                <div class="modal-grid" style="margin-bottom:1.2rem;">
                    <div class="form-group-modal">
                        <label>Estado</label>
                        <p id="vpr-estado" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Ciudad</label>
                        <p id="vpr-ciudad" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Calle</label>
                        <p id="vpr-calle" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Colonia</label>
                        <p id="vpr-colonia" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Número</label>
                        <p id="vpr-numero" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Código Postal</label>
                        <p id="vpr-cp" class="detalle-valor">—</p>
                    </div>
                </div>

            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" id="btn-cerrar-ver-profesor">Cerrar</button>
        </div>

    </div>
</div>