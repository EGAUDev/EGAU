<!-- ===================== SECCIÓN: PERSONAL ===================== -->
<section class="section-content" id="section-personal" style="display: none;">

    <div class="section-header">
        <h1 class="section-title">Gestión de Personal</h1>
      @if($permisos->personal_edit ?? false)
      <button class="btn-primary" id="btn-agregar-personal">
        <i class="ri-add-line"></i> Agregar Personal
    </button>
    @endif  
    </div>

    <div class="card">
        <div class="controls-container">
            <div class="search-bar">
                <i class="ri-search-line search-icon"></i>
                <input type="text" id="buscador-personal" placeholder="Buscar personal..." autocomplete="off">
            </div>
            <div class="filters-row">
                <div class="select-wrapper custom-dropdown" id="dropdown-nivel-personal">
                    <div class="custom-select-trigger">
                        <span class="selected-text" data-value="">Todos los roles</span>
                        <i class="ri-arrow-down-s-line"></i>
                    </div>
                    <div class="custom-options-container">
                        <div class="custom-option selected" data-value="">Todos los roles</div>
                        @foreach($roles as $r)
                        <div class="custom-option" data-value="{{ $r->id_rol }}">{{ $r->nombre }}</div>
                        @endforeach
                    </div>
                </div>
                <div class="select-wrapper custom-dropdown" id="dropdown-status-personal">
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
                <div class="select-wrapper custom-dropdown" id="dropdown-sede-personal">
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
                <button id="btn-limpiar-personal" class="btn-clear-filters">Limpiar filtros</button>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="data-table" id="tabla-personal">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Sede</th>
                        <th>Status</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($personal as $p)
                    <tr>
                        <td>{{ $p->id_personal }}</td>
                        <td>{{ $p->nombre }} {{ $p->apellido_p }} {{ $p->apellido_m }}</td>
                        <td>{{ $p->nombre_rol ?? 'Sin rol' }}</td>
                        <td>{{ $p->ciudad ?? 'Sede Central' }}</td>
                        <td>
                            @if($p->estatus)
                                <span class="badge badge-activo">Activo</span>
                            @else
                                <span class="badge badge-inactivo">Inactivo</span>
                            @endif
                        </td>
                        <td class="acciones">
                            <button class="btn-icon btn-ver btn-ver-personal" title="Ver" data-id="{{ $p->id_personal }}" data-info="{{ json_encode($p) }}"><i class="ri-eye-line"></i></button>
                            <button class="btn-icon btn-editar btn-editar-personal" title="Editar" data-id="{{ $p->id_personal }}" data-info="{{ json_encode($p) }}"><i class="ri-edit-line"></i></button>
                            <button class="btn-icon btn-eliminar btn-eliminar-personal" title="Eliminar" data-id="{{ $p->id_personal }}"><i class="ri-delete-bin-line"></i></button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <div class="pagination-info">Mostrando 1 resultado</div>
            <div class="pagination-btns">
                <button class="pag-btn" disabled><i class="ri-arrow-left-s-line"></i></button>
                <button class="pag-btn active">1</button>
                <button class="pag-btn" disabled><i class="ri-arrow-right-s-line"></i></button>
            </div>
        </div>

    </div>
</section>

<!-- ===================== MODAL: AGREGAR PERSONAL ===================== -->
<div class="modal-overlay" id="modal-agregar-personal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title-group">
                <i class="ri-user-add-line modal-title-icon"></i>
                <h2 class="modal-title">Agregar Personal</h2>
            </div>
            <button class="modal-close-btn" id="modal-close-personal" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="form-agregar-personal" novalidate>
                <div class="modal-section-label">
                    <i class="ri-id-card-line"></i> Información del Personal
                </div>
                <div class="modal-grid">
                    <div class="form-group-modal">
                        <label for="pe-nombre">Nombre <span style="color:var(--naranja)">*</span></label>
                        <input type="text" id="pe-nombre" name="pe_nombre" placeholder="Nombre(s)" required>
                        <span class="error-msg-modal" id="err-pe-nombre"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pe-ap-paterno">Apellido Paterno <span style="color:var(--naranja)">*</span></label>
                        <input type="text" id="pe-ap-paterno" name="pe_ap_paterno" placeholder="Apellido paterno" required>
                        <span class="error-msg-modal" id="err-pe-ap-paterno"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pe-ap-materno">Apellido Materno</label>
                        <input type="text" id="pe-ap-materno" name="pe_ap_materno" placeholder="Apellido materno">
                    </div>
                    <div class="form-group-modal">
                        <label for="pe-fecha-nacimiento">Fecha de Nacimiento <span style="color:var(--naranja)">*</span></label>
                        <input type="date" id="pe-fecha-nacimiento" name="pe_fecha_nacimiento" required>
                        <span class="error-msg-modal" id="err-pe-fecha-nacimiento"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pe-telefono">Número de Teléfono</label>
                        <input type="tel" id="pe-telefono" name="pe_telefono" placeholder="10 dígitos" maxlength="10">
                        <span class="error-msg-modal" id="err-pe-telefono"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pe-genero">Género</label>
                        <div class="form-dropdown" id="dropdown-pe-genero" tabindex="0">
                            <div class="form-select-trigger">
                                <span class="selected-text" data-value="">Selecciona una opción</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                <div class="form-option" data-value="f">Femenino (F)</div>
                                <div class="form-option" data-value="m">Masculino (M)</div>
                                <div class="form-option" data-value="o">Otro (O)</div>
                            </div>
                            <input type="hidden" id="pe-genero" name="pe_genero" value="">
                        </div>
                        <span class="error-msg-modal" id="err-pe-genero"></span>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="pe-sede">Sede <span style="color:var(--naranja)">*</span></label>
                        <div class="form-dropdown" id="dropdown-pe-sede" tabindex="0">
                            <div class="form-select-trigger">
                                <span class="selected-text" data-value="">Selecciona una sede</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                @foreach($sedes as $s)
                                <div class="form-option" data-value="{{ $s->id_sede }}">{{ $s->nombre }}</div>
                                @endforeach
                            </div>
                            <input type="hidden" id="pe-sede" name="pe_sede" value="">
                        </div>
                        <span class="error-msg-modal" id="err-pe-sede"></span>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="pe-rol">Rol <span style="color:var(--naranja)">*</span></label>
                        <div class="form-dropdown" id="dropdown-pe-rol" tabindex="0">
                            <div class="form-select-trigger">
                                <span class="selected-text" data-value="">Selecciona una opción</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                @foreach($roles as $r)
                                <div class="form-option" data-value="{{ $r->id_rol }}">{{ $r->nombre }}</div>
                                @endforeach
                            </div>
                            <input type="hidden" id="pe-rol" name="pe_rol" value="">
                        </div>
                        <span class="error-msg-modal" id="err-pe-rol"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pe-estado">Estado <span style="color:var(--naranja)">*</span></label>
                        @include('admin.partials.dropdown-estados', ['prefix' => 'pe'])
                        <span class="error-msg-modal" id="err-pe-estado"></span>
                    </div>
                    <div class="form-group-modal">
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 2;">
                                <label for="pe-ciudad">Ciudad <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="pe-ciudad" name="pe_ciudad" placeholder="Ciudad" required>
                                <span class="error-msg-modal" id="err-pe-ciudad"></span>
                            </div>
                            <div style="flex: 1;">
                                <label for="pe-cp">CP <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="pe-cp" name="pe_cp" placeholder="CP" maxlength="5" required>
                                <span class="error-msg-modal" id="err-pe-cp"></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 2;">
                                <label for="pe-calle">Calle <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="pe-calle" name="pe_calle" placeholder="Nombre de la calle" required>
                                <span class="error-msg-modal" id="err-pe-calle"></span>
                            </div>
                            <div style="flex: 2;">
                                <label for="pe-colonia">Colonia <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="pe-colonia" name="pe_colonia" placeholder="Colonia" required>
                                <span class="error-msg-modal" id="err-pe-colonia"></span>
                            </div>
                            <div style="flex: 1;">
                                <label for="pe-numero">Número <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="pe-numero" name="pe_numero" placeholder="# Ext/Int" required>
                                <span class="error-msg-modal" id="err-pe-numero"></span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group-modal modal-col-full">
                        <label for="pe-correo">Correo Electrónico <span style="color:var(--naranja)">*</span></label>
                        <input type="email" id="pe-correo" name="pe_correo" placeholder="correo@ejemplo.com" required>
                        <span class="error-msg-modal" id="err-pe-correo"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pe-password">Contraseña <span style="color:var(--naranja)">*</span></label>
                        <div class="input-password-wrapper">
                            <input type="password" id="pe-password" name="pe_password" placeholder="Mínimo 6 caracteres" required>
                            <button type="button" class="toggle-password" data-target="pe-password">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                        <span class="error-msg-modal" id="err-pe-password"></span>
                    </div>
                    <div class="form-group-modal">
                        <label for="pe-password-confirm">Confirmar Contraseña <span style="color:var(--naranja)">*</span></label>
                        <div class="input-password-wrapper">
                            <input type="password" id="pe-password-confirm" name="pe_password_confirm" placeholder="Repite la contraseña" required>
                            <button type="button" class="toggle-password" data-target="pe-password-confirm">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                        <span class="error-msg-modal" id="err-pe-password-confirm"></span>
                    </div>

                    {{-- ===== ESTATUS ===== --}}
                    <div class="form-group-modal">
                        <label for="pe-estatus">Estatus</label>
                        <div class="form-dropdown" id="dropdown-pe-estatus" tabindex="0">
                            <div class="form-select-trigger">
                                <span class="selected-text" data-value="1">Activo</span>
                                <i class="ri-arrow-down-s-line"></i>
                            </div>
                            <div class="form-options-container">
                                <div class="form-option selected" data-value="1">Activo</div>
                                <div class="form-option" data-value="0">Inactivo</div>
                            </div>
                            <input type="hidden" id="pe-estatus" name="pe_estatus" value="1">
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" id="btn-cancelar-modal-personal">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="ri-save-line"></i> Guardar Personal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================== MODAL: VER DETALLE PERSONAL ===================== -->
<div class="modal-overlay" id="modal-ver-personal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title-group">
                <i class="ri-eye-line modal-title-icon"></i>
                <h2 class="modal-title">Detalle del Personal</h2>
            </div>
            <button class="modal-close-btn" id="modal-close-ver-personal" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="ver-personal-loader" style="text-align:center; padding:2rem;">
                <i class="ri-loader-4-line ri-spin" style="font-size:2rem; color:var(--naranja)"></i>
                <p style="margin-top:.5rem; color:var(--texto-secundario)">Cargando datos...</p>
            </div>
            <div id="ver-personal-content" style="display:none;">

                <div class="modal-section-label"><i class="ri-user-line"></i> Datos Personales</div>
                <div class="modal-grid" style="margin-bottom:1.2rem;">
                    <div class="form-group-modal">
                        <label>Nombre completo</label>
                        <p id="vpe-nombre" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Fecha de Nacimiento</label>
                        <p id="vpe-fecha-nac" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Edad</label>
                        <p id="vpe-edad" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Género</label>
                        <p id="vpe-genero" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Teléfono</label>
                        <p id="vpe-telefono" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Correo</label>
                        <p id="vpe-correo" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Estatus</label>
                        <p id="vpe-estatus" class="detalle-valor">—</p>
                    </div>
                </div>

                <div class="modal-section-label"><i class="ri-id-card-line"></i> Datos Laborales</div>
                <div class="modal-grid" style="margin-bottom:1.2rem;">
                    <div class="form-group-modal">
                        <label>Rol</label>
                        <p id="vpe-rol" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Sede</label>
                        <p id="vpe-sede" class="detalle-valor">—</p>
                    </div>
                </div>

                <div class="modal-section-label"><i class="ri-map-pin-line"></i> Dirección</div>
                <div class="modal-grid" style="margin-bottom:1.2rem;">
                    <div class="form-group-modal">
                        <label>Estado</label>
                        <p id="vpe-estado" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Ciudad</label>
                        <p id="vpe-ciudad" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Calle</label>
                        <p id="vpe-calle" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Colonia</label>
                        <p id="vpe-colonia" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Número</label>
                        <p id="vpe-numero" class="detalle-valor">—</p>
                    </div>
                    <div class="form-group-modal">
                        <label>Código Postal</label>
                        <p id="vpe-cp" class="detalle-valor">—</p>
                    </div>
                </div>

            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" id="btn-cerrar-ver-personal">Cerrar</button>
        </div>
    </div>
</div>