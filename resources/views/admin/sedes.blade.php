{{-- ===================== SECCIÓN: SEDES ===================== --}}
<section class="section-content" id="section-sede" style="display: none;">

    <div class="section-header">
        <h1 class="section-title">Gestión de Sedes</h1>
        @if($permisos->sedes_edit ?? false)
            <button class="btn-primary" id="btn-agregar-sede">
                <i class="ri-add-line"></i> Agregar Sede
            </button>
        @endif          
    </div>

    <div class="card card-sedes">
        <div class="controls-container" style="padding: 24px;">
            <div class="search-bar search-sede">
                <i class="ri-search-line search-icon"></i>
                <input type="text" id="buscador-sedes" placeholder="Buscar sede por nombre o dirección..."
                    autocomplete="off">
            </div>
            <div class="filters-row">
                <div class="select-wrapper custom-dropdown" id="dropdown-filtro-estado-sede">
                    <div class="custom-select-trigger">
                        <span class="selected-text" data-value="">Todos los estados</span>
                        <i class="ri-arrow-down-s-line"></i>
                    </div>
                    <div class="custom-options-container" style="max-height: 200px; overflow-y: auto;">
                        <div class="custom-option selected" data-value="">Todos los estados</div>
                        <div class="custom-option" data-value="Aguascalientes">Aguascalientes</div>
                        <div class="custom-option" data-value="Baja California">Baja California</div>
                        <div class="custom-option" data-value="Baja California Sur">Baja California Sur</div>
                        <div class="custom-option" data-value="Campeche">Campeche</div>
                        <div class="custom-option" data-value="Chiapas">Chiapas</div>
                        <div class="custom-option" data-value="Chihuahua">Chihuahua</div>
                        <div class="custom-option" data-value="Ciudad de México">Ciudad de México</div>
                        <div class="custom-option" data-value="Coahuila">Coahuila</div>
                        <div class="custom-option" data-value="Colima">Colima</div>
                        <div class="custom-option" data-value="Durango">Durango</div>
                        <div class="custom-option" data-value="Estado de México">Estado de México</div>
                        <div class="custom-option" data-value="Guanajuato">Guanajuato</div>
                        <div class="custom-option" data-value="Guerrero">Guerrero</div>
                        <div class="custom-option" data-value="Hidalgo">Hidalgo</div>
                        <div class="custom-option" data-value="Jalisco">Jalisco</div>
                        <div class="custom-option" data-value="Michoacán">Michoacán</div>
                        <div class="custom-option" data-value="Morelos">Morelos</div>
                        <div class="custom-option" data-value="Nayarit">Nayarit</div>
                        <div class="custom-option" data-value="Nuevo León">Nuevo León</div>
                        <div class="custom-option" data-value="Oaxaca">Oaxaca</div>
                        <div class="custom-option" data-value="Puebla">Puebla</div>
                        <div class="custom-option" data-value="Querétaro">Querétaro</div>
                        <div class="custom-option" data-value="Quintana Roo">Quintana Roo</div>
                        <div class="custom-option" data-value="San Luis Potosí">San Luis Potosí</div>
                        <div class="custom-option" data-value="Sinaloa">Sinaloa</div>
                        <div class="custom-option" data-value="Sonora">Sonora</div>
                        <div class="custom-option" data-value="Tabasco">Tabasco</div>
                        <div class="custom-option" data-value="Tamaulipas">Tamaulipas</div>
                        <div class="custom-option" data-value="Tlaxcala">Tlaxcala</div>
                        <div class="custom-option" data-value="Veracruz">Veracruz</div>
                        <div class="custom-option" data-value="Yucatán">Yucatán</div>
                        <div class="custom-option" data-value="Zacatecas">Zacatecas</div>
                    </div>
                </div>
                <button id="btn-limpiar-sedes" class="btn-clear-filters">Limpiar filtros</button>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="data-table" id="tabla-sedes">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Dirección</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Se llena dinámicamente desde dashboardAdminSede.js --}}
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <div class="pagination-info">Mostrando 0 resultados</div>
            <div class="pagination-btns">
                <button class="pag-btn" disabled><i class="ri-arrow-left-s-line"></i></button>
                <button class="pag-btn active">1</button>
                <button class="pag-btn" disabled><i class="ri-arrow-right-s-line"></i></button>
            </div>
        </div>
    </div>
</section>

{{-- ===================== MODAL: AGREGAR / EDITAR SEDE ===================== --}}
<div class="modal-overlay" id="modal-agregar-sede">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-title-icon"><i class="ri-map-pin-line"></i></span>
                <h2 class="modal-title" id="modal-sede-titulo">Agregar Sede</h2>
            </div>
            <button type="button" class="modal-close-btn" id="modal-close-sede" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="form-agregar-sede" novalidate>
                <div class="modal-grid">
                    <div class="form-group-modal modal-col-full">
                        <label for="se-nombre">Nombre de la Sede <span style="color:var(--naranja)">*</span></label>
                        <input type="text" id="se-nombre" name="se_nombre" placeholder="Ej. Sede Central" required>
                        <span class="error-msg-modal" id="err-se-nombre"></span>
                    </div>

                    <div class="form-group-modal">
                        <label for="se-estado">Estado <span style="color:var(--naranja)">*</span></label>
                        @include('admin.partials.dropdown-estados', ['prefix' => 'se'])
                        <span class="error-msg-modal" id="err-se-estado"></span>
                    </div>

                    <div class="form-group-modal">
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 2;">
                                <label for="se-ciudad">Ciudad <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="se-ciudad" name="se_ciudad" placeholder="Ciudad" required>
                                <span class="error-msg-modal" id="err-se-ciudad"></span>
                            </div>
                            <div style="flex: 1;">
                                <label for="se-cp">CP <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="se-cp" name="se_cp" placeholder="CP" maxlength="5" required>
                                <span class="error-msg-modal" id="err-se-cp"></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-modal modal-col-full">
                        <label for="se-calle">Dirección (Calle y Número) <span style="color:var(--naranja)">*</span></label>
                        <input type="text" id="se-calle" name="se_calle" placeholder="Calle, número, colonia..." required>
                        <span class="error-msg-modal" id="err-se-calle"></span>
                    </div>

                    <div class="form-group-modal">
                        <label for="se-telefono">Teléfono <span style="color:var(--naranja)">*</span></label>
                        <input type="tel" id="se-telefono" name="se_telefono" placeholder="10 dígitos" maxlength="10" required>
                        <span class="error-msg-modal" id="err-se-telefono"></span>
                    </div>

                    <div class="form-group-modal">
                        <label for="se-correo">Email de Contacto <span style="color:var(--naranja)">*</span></label>
                        <input type="email" id="se-correo" name="se_correo" placeholder="correo@egau.com" required>
                        <span class="error-msg-modal" id="err-se-correo"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" id="btn-cancelar-modal-sede">Cancelar</button>
                    <button type="submit" class="btn-modal-submit">
                        <i class="ri-save-line"></i> Guardar Sede
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== MODAL: VER DETALLE SEDE ===================== --}}
<div class="modal-overlay" id="modal-ver-sede">
    <div class="modal-box" style="max-width: 500px;">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-title-icon"><i class="ri-eye-line"></i></span>
                <h2 class="modal-title">Detalle de la Sede</h2>
            </div>
            <button type="button" class="modal-close-btn" id="modal-close-ver-sede" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-section-label"><i class="ri-map-pin-line"></i> Información de la Sede</div>
            <div class="modal-grid">
                <div class="form-group-modal modal-col-full">
                    <label>Nombre</label>
                    <p class="detalle-valor" id="vse-nombre">—</p>
                </div>
                <div class="form-group-modal">
                    <label>Estado</label>
                    <p class="detalle-valor" id="vse-estado">—</p>
                </div>
                <div class="form-group-modal">
                    <label>Ciudad</label>
                    <p class="detalle-valor" id="vse-ciudad">—</p>
                </div>
                <div class="form-group-modal">
                    <label>Código Postal</label>
                    <p class="detalle-valor" id="vse-cp">—</p>
                </div>
                <div class="form-group-modal modal-col-full">
                    <label>Dirección</label>
                    <p class="detalle-valor" id="vse-calle">—</p>
                </div>
                <div class="form-group-modal">
                    <label>Teléfono</label>
                    <p class="detalle-valor" id="vse-telefono">—</p>
                </div>
                <div class="form-group-modal">
                    <label>Email</label>
                    <p class="detalle-valor" id="vse-email">—</p>
                </div>
                <div class="form-group-modal">
                    <label>Estatus</label>
                    <p class="detalle-valor" id="vse-estatus">—</p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" id="btn-cerrar-ver-sede">Cerrar</button>
        </div>
    </div>
</div>