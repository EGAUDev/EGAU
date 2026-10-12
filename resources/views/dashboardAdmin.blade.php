<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-token" content="{{ session('token') }}">
    <title>Dashboard - EGAU Chess</title>
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- Iconos (Remix Icons) -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <!-- Hojas de estilos -->
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdmin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminPersonal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminAlumnos.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminProfesores.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminSede.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminGrupos.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminExtraescolares.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminNiveles.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminRoles.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminFacturas.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/dashboardAdminBiblioteca.css') }}">
</head>

<body>

    <!-- Pantalla de carga -->
    <div id="loading-screen" style="
    position: fixed; inset: 0; z-index: 9999;
    background-color: #f4f6f9;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 16px;
    transition: opacity 0.4s ease;">
        <img src="{{ asset('Logos/LogoEgau.png') }}" id="loading-logo" style="width: 80px; opacity: 0.8;">
        <span style="font-family:'Cinzel',serif; font-size: 13px; color: #888; letter-spacing: 3px;">
            CARGANDO<span class="loading-dot">.</span><span class="loading-dot">.</span><span class="loading-dot">.</span>
        </span>
    </div>
@php $esProfesor = ($tipo ?? 'personal') === 'profesor'; @endphp
    <!-- ===================== SIDEBAR ===================== -->
    <aside class="sidebar" id="sidebar">

        <!-- Logo / Nombre -->
        <div class="sidebar-logo">
            <img src="{{ asset('Logos/LogoEgau.png') }}" alt="EGAU Chess" class="sidebar-logo-img">
            <span class="sidebar-brand">EGAU Chess</span>
        </div>

        <nav class="sidebar-nav">
    <ul>
        @if(!$esProfesor)
            @if($permisos->alumno_ver ?? false)
                <li class="nav-item active" data-section="alumnos">
                    <a href="#"><i class="ri-group-line"></i><span>Alumnos</span></a>
                </li>
            @endif
            @if($permisos->profesor_ver ?? false)
                <li class="nav-item" data-section="profesores">
                    <a href="#"><i class="ri-user-star-line"></i><span>Profesores</span></a>
                </li>
            @endif
            @if($permisos->personal_ver ?? false)
                <li class="nav-item" data-section="personal">
                    <a href="#"><i class="ri-user-settings-line"></i><span>Personal</span></a>
                </li>
            @endif
            @if($permisos->roles_ver ?? false)
                <li class="nav-item" data-section="roles">
                    <a href="#"><i class="ri-shield-user-line"></i><span>Roles</span></a>
                </li>
            @endif
            @if($permisos->sedes_ver ?? false)
                <li class="nav-item" data-section="sede">
                    <a href="#"><i class="ri-map-pin-line"></i><span>Sede</span></a>
                </li>
            @endif
        @endif

        @if($esProfesor || ($permisos->grupos_ver ?? false))
            <li class="nav-item {{ $esProfesor ? 'active' : '' }}" data-section="grupos">
                <a href="#"><i class="ri-grid-line"></i><span>Grupos</span></a>
            </li>
        @endif

        @if(!$esProfesor)
            @if($permisos->extracurriculares_ver ?? false)
                <li class="nav-item" data-section="extraescolares">
                    <a href="#"><i class="ri-bar-chart-2-line"></i><span>Extraescolares</span></a>
                </li>
            @endif
            @if($permisos->estatus_ver ?? false)
                <li class="nav-item" data-section="status">
                    <a href="#"><i class="ri-bookmark-line"></i><span>Status</span></a>
                </li>
            @endif
            @if($permisos->pagos_ver ?? false)
                <li class="nav-item" data-section="pagos">
                    <a href="#"><i class="ri-bank-card-line"></i><span>Pagos</span></a>
                </li>
            @endif
        @endif

        @if($esProfesor || ($permisos->niveles_ver ?? false))
            <li class="nav-item" data-section="niveles">
                <a href="#"><i class="ri-book-open-line"></i><span>Niveles</span></a>
            </li>
        @endif

        {{-- Biblioteca: visible para todo el personal, sin depender de permisos --}}
        <li class="nav-item" data-section="biblioteca">
            <a href="#"><i class="ri-book-shelf-line"></i><span>Biblioteca</span></a>
        </li>
    </ul>
</nav>

        <!-- Opciones al fondo -->
        <div class="sidebar-footer">
            <a href="#" class="nav-footer-item nav-item" data-section="opciones">
                <i class="ri-settings-3-line"></i>
                <span>Configuración</span>
            </a>
        </div>

    </aside>

    <!-- ===================== CONTENIDO PRINCIPAL ===================== -->
    <main class="main-content">

        <!-- Topbar -->
        <header class="topbar">
            <!-- Botón hamburguesa -->
            <button class="menu-toggle" id="menu-toggle" title="Ocultar/Mostrar menú">
                <i class="ri-menu-line"></i>
            </button>
            <div class="topbar-right">
                <div class="profile-menu-wrapper" id="profile-menu">
                    <!-- Área clickeable (Nombre + Círculo) -->
                    <div class="profile-trigger">
                        <span class="admin-name">Administrador</span>
                        <div class="avatar">A</div>
                    </div>

                    <!-- Menú desplegable flotante -->
                    <div class="profile-dropdown">
                        <div class="profile-header">
                            <span class="profile-name">Administrador</span>
                            <span class="profile-email">admin@egau.com</span>
                        </div>
                        <ul class="profile-options">
                            <li id="btn-config-perfil"><i class="ri-settings-3-line"></i> Configuración</li>
                            <li id="btn-logout" class="text-danger"><i class="ri-logout-box-r-line"></i> Cerrar sesión
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>



        {{-- ===================== ROLES ===================== --}}
        <script>
            window.PERMISOS_ROLES = {
                ver:   {{ ($permisos->roles_ver  ?? false) ? 'true' : 'false' }},
                edit:  {{ ($permisos->roles_edit ?? false) ? 'true' : 'false' }},
                admin: {{ ($administrativo ?? false) ? 'true' : 'false' }}
            };
        </script>
        @include('admin.roles')





        {{-- ===================== ALUMNOS ===================== --}}
        <script>
            window.PERMISOS_ALUMNOS = {
                edit: {{ ($permisos->alumno_edit ?? false) ? 'true' : 'false' }},
                admin: {{ ($administrativo ?? false) ? 'true' : 'false' }}
    };
        </script>

        @include('admin.alumnos')

        {{-- ===================== PROFESORES ===================== --}}
        <script>
            window.PERMISOS_PROFESORES = {
                edit: {{ ($permisos->profesor_edit ?? false) ? 'true' : 'false' }},
                admin: {{ ($administrativo ?? false) ? 'true' : 'false' }}
    };
        </script>
        @include('admin.profesores')

        {{-- ===================== PERSONAL ===================== --}}
        <script>
            window.PERMISOS_PERSONAL = {
                edit: {{ ($permisos->personal_edit ?? false) ? 'true' : 'false' }},
                admin: {{ ($administrativo ?? false) ? 'true' : 'false' }}
    };
        </script>
        @include('admin.personal')

        {{-- ===================== SEDES ===================== --}}
        <script>
            window.PERMISOS_SEDES = {
                ver:  {{ ($permisos->sedes_ver ?? false) ? 'true' : 'false' }},
                edit: {{ ($permisos->sedes_edit ?? false) ? 'true' : 'false' }},
                admin: {{ ($administrativo ?? false) ? 'true' : 'false' }}
    };
        </script>
        @include('admin.sedes')

        {{-- ===================== GRUPOS ===================== --}}
        <script>
    window.PERMISOS_GRUPOS = {
        ver:   {{ $esProfesor ? 'true' : (($permisos->grupos_ver  ?? false) ? 'true' : 'false') }},
        edit:  {{ $esProfesor ? 'false' : (($permisos->grupos_edit ?? false) ? 'true' : 'false') }},
        admin: {{ ($administrativo ?? false) ? 'true' : 'false' }}
    };
    window.ID_PROFESOR = {{ $idProfesor ?? 'null' }};
    window.ES_PROFESOR = {{ $esProfesor ? 'true' : 'false' }};
</script>
        <!-- ======================= SECCIÓN GRUPOS ======================= -->
        <section class="section-content" id="section-grupos" style="display: none;">

            <!-- Cabecera de sección -->
            <div class="section-header">
                <h1 class="section-title">{{ $esProfesor ? 'Grupos' : 'Grupos y Cursos' }}</h1> 
                @if($permisos->grupos_edit ?? false)
                    <div style="display: flex; gap: 12px;">
                        <button class="btn-secondary" id="btn-agregar-curso"><i class="ri-book-open-line"></i> Crear
                            Curso</button>
                        <button class="btn-primary" id="btn-agregar-grupo"><i class="ri-add-line"></i> Crear Grupo</button>
                    </div>
                @endif
            </div>

            <!-- Buscador -->
            <div class="search-bar" style="margin-bottom: 24px;">
                <i class="ri-search-line search-icon"></i>
                <input type="text" id="buscador-grupos" placeholder="Buscar grupo por nombre, nivel o profesor..."
                    autocomplete="off">
            </div>

            <!-- Grid de tarjetas de grupos -->
            <div class="grupos-grid" id="grid-grupos">
                <!-- Las tarjetas se generan dinámicamente por dashboardAdminGrupos.js -->
            </div>
            <!-- Mensaje sin resultados -->
            <p class="grupos-empty" id="grupos-empty" style="display:none;">No se encontraron grupos.</p>

          @if(!$esProfesor)
            <!-- ======================= TABLA DEMOSTRATIVA DE CURSOS ======================= -->
          
            <div style="margin-top: 40px;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 24px;">
                    <h2
                        style="font-family: 'Inter', sans-serif; font-size: 18px; color: var(--texto); margin: 0; white-space: nowrap;">
                        Catálogo de Cursos</h2>
                    <div class="search-bar" style="flex-grow: 1; max-width: 600px; margin: 0;">
                        <i class="ri-search-line search-icon"></i>
                        <input type="text" id="buscador-tabla-cursos" placeholder="Buscar por clave, nombre o sede..."
                            autocomplete="off">
                    </div>
                </div>

                <div
                    style="overflow-x: auto; background-color: var(--blanco); border-radius: 12px; border: 1px solid var(--borde); box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
                    <table id="tabla-cursos" style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead style="background-color: #fafafa; border-bottom: 1px solid var(--borde);">
                            <tr>
                                <th
                                    style="padding: 16px; font-weight: 600; font-size: 13px; color: var(--texto-suave);">
                                    ID</th>
                                <th
                                    style="padding: 16px; font-weight: 600; font-size: 13px; color: var(--texto-suave);">
                                    Nombre del Curso</th>
                                <th
                                    style="padding: 16px; font-weight: 600; font-size: 13px; color: var(--texto-suave);">
                                    Nivel</th>
                                <th
                                    style="padding: 16px; font-weight: 600; font-size: 13px; color: var(--texto-suave);">
                                    Duración</th>
                                <th
                                    style="padding: 16px; font-weight: 600; font-size: 13px; color: var(--texto-suave);">
                                    Costo Base</th>
                                <th
                                    style="padding: 16px; font-weight: 600; font-size: 13px; color: var(--texto-suave);">
                                    Sede</th>
                                <th
                                    style="padding: 16px; font-weight: 600; font-size: 13px; color: var(--texto-suave);">
                                    Estatus</th>
                                <th
                                    style="padding: 16px; font-weight: 600; font-size: 13px; color: var(--texto-suave); text-align: center;">
                                    Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-cursos">
                            <!-- Filas generadas por dashboardAdminGrupos.js -->
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </section>

        <!-- ======================= MODAL: AGREGAR GRUPO ======================= -->
        <div class="modal-overlay" id="modal-agregar-grupo">
            <div class="modal-box modal-box-large"> <!-- Una clase extra por si es más ancho debido a los horarios -->

                <div class="modal-header">
                    <div class="modal-title-group">
                        <span class="modal-title-icon"><i class="ri-group-line"></i></span>
                        <h2 class="modal-title">Crear Nuevo Grupo</h2>
                    </div>
                    <button type="button" class="modal-close-btn" id="modal-close-grupo" title="Cerrar">
                        <i class="ri-close-line"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <form id="form-agregar-grupo" novalidate>
                        @csrf

                        <!-- ===== DATOS DEL GRUPO ===== -->
                        <div class="modal-section-label">
                            <i class="ri-information-line"></i> Datos Generales
                        </div>
                        <div class="modal-grid">

                            <!-- Curso (Dropdown en vez de ID) -->
                            <div class="form-group-modal">
                                <label for="gr-curso">Curso <span style="color:var(--naranja)">*</span></label>
                                <div class="form-dropdown" id="dropdown-gr-curso" tabindex="0">
                                    <div class="form-select-trigger" id="trigger-gr-curso">
                                        <span class="selected-text" data-value="">Selecciona un curso...</span>
                                        <i class="ri-arrow-down-s-line"></i>
                                    </div>
                                    <div class="form-options-container" id="opciones-gr-curso">
                                        <!-- Se llena dinámicamente via AJAX -->
                                    </div>
                                    <input type="hidden" id="gr-curso" name="id_curso" value="">
                                </div>
                                <span class="error-msg-modal" id="err-gr-curso"></span>
                            </div>

                            <!-- Instructor (Dropdown en vez de ID) -->
                            <div class="form-group-modal">
                                <label for="gr-instructor">Instructor <span
                                        style="color:var(--naranja)">*</span></label>
                                <div class="form-dropdown" id="dropdown-gr-instructor" tabindex="0">
                                    <div class="form-select-trigger" id="trigger-gr-instructor">
                                        <span class="selected-text" data-value="">Asignar un profesor...</span>
                                        <i class="ri-arrow-down-s-line"></i>
                                    </div>
                                    <div class="form-options-container" id="opciones-gr-instructor">
                                        <!-- Se llena dinámicamente via AJAX -->
                                    </div>
                                    <input type="hidden" id="gr-instructor" name="id_instructor" value="">
                                </div>
                                <span class="error-msg-modal" id="err-gr-instructor"></span>
                            </div>

                            <div class="form-group-modal">
                                <label for="gr-codigo">Código de Grupo <span
                                        style="color:var(--naranja)">*</span></label>
                                <input type="text" id="gr-codigo" name="codigo_grupo" placeholder="Ej. AJE-PRIN-01"
                                    required>
                                <span class="error-msg-modal" id="err-gr-codigo"></span>
                            </div>
                            <div class="form-group-modal">
                                <label for="gr-nombre">Nombre del Grupo</label>
                                <input type="text" id="gr-nombre" name="nombre" placeholder="Ej. Grupo Principiantes Tarde">
                            </div>
                            <div class="form-group-modal">
                                <label for="gr-periodo">Periodo</label>
                                <input type="text" id="gr-periodo" name="periodo" placeholder="Ej. 2026-A"
                                    maxlength="10">
                            </div>

                            <div class="form-group-modal">
                                <label for="gr-fecha-inicio">Fecha de Inicio <span
                                        style="color:var(--naranja)">*</span></label>
                                <input type="date" id="gr-fecha-inicio" name="fecha_inicio" required>
                                <span class="error-msg-modal" id="err-gr-fecha-inicio"></span>
                            </div>

                            <div class="form-group-modal">
                                <label for="gr-fecha-fin">Fecha de Fin <span
                                        style="color:var(--naranja)">*</span></label>
                                <input type="date" id="gr-fecha-fin" name="fecha_fin" required>
                                <span class="error-msg-modal" id="err-gr-fecha-fin"></span>
                            </div>

                            <div class="form-group-modal">
                                <label for="gr-cupo-maximo">Cupo Máximo <span
                                        style="color:var(--naranja)">*</span></label>
                                <input type="number" id="gr-cupo-maximo" name="cupo_maximo"
                                    placeholder="Número de estudiantes" min="1" required>
                                <span class="error-msg-modal" id="err-gr-cupo-maximo"></span>
                            </div>

                            <div class="form-group-modal">
                                <label for="gr-estatus-general">Estatus Módulo</label>
                                <div class="form-dropdown" id="dropdown-gr-estatus" tabindex="0">
                                    <div class="form-select-trigger" id="trigger-gr-estatus">
                                        <span class="selected-text" data-value="activo">Activo</span>
                                        <i class="ri-arrow-down-s-line"></i>
                                    </div>
                                    <div class="form-options-container">
                                        <div class="form-option selected" data-value="activo">Activo</div>
                                        <div class="form-option" data-value="inactivo">Inactivo</div>
                                        <div class="form-option" data-value="cerrado">Cerrado</div>
                                    </div>
                                    <input type="hidden" id="gr-estatus-general" name="estatus" value="activo">
                                </div>
                            </div>



                        </div>

                        <!-- ===== HORARIOS (DINÁMICOS) ===== -->
                        <div class="modal-section-label"
                            style="margin-top:20px; display:flex; justify-content:space-between; align-items:center;">
                            <span><i class="ri-calendar-todo-fill"></i> Horarios de Clases</span>
                            <button type="button" class="btn-agregar-horario" id="btn-add-horario">
                                <i class="ri-add-line"></i> Añadir día
                            </button>
                        </div>
                        <p style="font-size:13px; color:var(--texto-suave); margin-bottom:12px;">Agrega los días de la
                            semana y las horas en las que se impartirá este grupo.</p>

                        <div class="horarios-container" id="horarios-list">
                            <!-- Aquí se insertan dinámicamente las filas de horarios -->
                        </div>
                        <span class="error-msg-modal" id="err-gr-horarios"
                            style="display:block; margin-top:5px; margin-bottom: 20px;"></span>


                        <!-- ===== PIE DEL FORMULARIO ===== -->
                        <div class="modal-footer">
                            <button type="button" class="btn-modal-cancel"
                                id="btn-cancelar-modal-grupo">Cancelar</button>
                            <button type="submit" class="btn-modal-submit">
                                <i class="ri-save-line"></i> Guardar Grupo
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <!-- ======================= MODAL: AGREGAR CURSO ======================= -->
        <div class="modal-overlay" id="modal-agregar-curso">
            <div class="modal-box">
                <div class="modal-header">
                    <div class="modal-title-group">
                        <span class="modal-title-icon"><i class="ri-book-open-line"></i></span>
                        <h2 class="modal-title">Crear Nuevo Curso</h2>
                    </div>
                    <button type="button" class="modal-close-btn" id="modal-close-curso" title="Cerrar">
                        <i class="ri-close-line"></i>
                    </button>
                </div>

                <div class="modal-body">
                    <form id="form-agregar-curso" novalidate>
                        @csrf

                        <div class="modal-section-label">
                            <i class="ri-information-line"></i> Datos del Curso
                        </div>
                        <div class="modal-grid">

                            <!-- Nombre -->
                            <div class="form-group-modal modal-col-full">
                                <label for="cu-nombre">Nombre <span style="color:var(--naranja)">*</span></label>
                                <input type="text" id="cu-nombre" name="nombre"
                                    placeholder="Ej. Táctica y Estrategia Avanzada" required>
                                <span class="error-msg-modal" id="err-cu-nombre"></span>
                            </div>

                            <!-- Sede (Dropdown) -->
                            <div class="form-group-modal">
                                <label for="cu-sede">Sede <span style="color:var(--naranja)">*</span></label>
                                <div class="form-dropdown" id="dropdown-cu-sede" tabindex="0">
                                    <div class="form-select-trigger" id="trigger-cu-sede">
                                        <span class="selected-text" data-value="">Selecciona una sede...</span>
                                        <i class="ri-arrow-down-s-line"></i>
                                    </div>
                                    <div class="form-options-container" id="opciones-cu-sede" style="z-index:100;">
                                        <!-- Se llena dinámicamente via AJAX -->
                                    </div>
                                    <input type="hidden" id="cu-sede" name="id_sede" value="">
                                </div>
                                <span class="error-msg-modal" id="err-cu-sede"></span>
                            </div>

                            <!-- Nivel -->
                            <div class="form-group-modal">
                                <label for="cu-nivel">Nivel</label>
                                <div class="form-dropdown" id="dropdown-cu-nivel" tabindex="0">
                                    <div class="form-select-trigger" id="trigger-cu-nivel">
                                        <span class="selected-text" data-value="">Selecciona un nivel...</span>
                                        <i class="ri-arrow-down-s-line"></i>
                                    </div>
                                    <div class="form-options-container" style="z-index:99;">
                                        <div class="form-option" data-value="0">♟ Peón</div>
                                        <div class="form-option" data-value="150">♞ Caballo</div>
                                        <div class="form-option" data-value="400">♝ Alfil</div>
                                        <div class="form-option" data-value="800">♜ Torre</div>
                                        <div class="form-option" data-value="1500">♛ Reina</div>
                                        <div class="form-option" data-value="3000">♚ Rey</div>
                                        </div>
                                    <input type="hidden" id="cu-nivel" name="nivel" value="">
                                </div>
                            </div>

                            <!-- Duración Semanas -->
                            <div class="form-group-modal">
                                <label for="cu-duracion">Duración (Semanas)</label>
                                <input type="number" id="cu-duracion" name="duracion_semanas" placeholder="Ej. 16"
                                    min="1">
                            </div>

                            <!-- Horas Totales -->
                            <div class="form-group-modal">
                                <label for="cu-horas">Horas Totales</label>
                                <input type="number" id="cu-horas" name="horas_totales" placeholder="Ej. 48" min="1">
                            </div>

                            <!-- Costo Base -->
                            <div class="form-group-modal">
                                <label for="cu-costo">Costo Base ($)</label>
                                <input type="number" id="cu-costo" name="costo_base" placeholder="Ej. 1500.00" min="0"
                                    step="0.01">
                            </div>

                            <!-- Estatus -->
                            <div class="form-group-modal">
                                <label for="cu-estatus">Estatus</label>
                                <div class="form-dropdown" id="dropdown-cu-estatus" tabindex="0">
                                    <div class="form-select-trigger" id="trigger-cu-estatus">
                                        <span class="selected-text" data-value="1">Activo</span>
                                        <i class="ri-arrow-down-s-line"></i>
                                    </div>
                                    <div class="form-options-container">
                                        <div class="form-option selected" data-value="1">Activo</div>
                                        <div class="form-option" data-value="0">Inactivo</div>
                                    </div>
                                    <input type="hidden" id="cu-estatus" name="estatus" value="1">
                                </div>
                            </div>

                            <!-- Descripción -->
                            <div class="form-group-modal modal-col-full">
                                <label for="cu-descripcion">Descripción</label>
                                <textarea id="cu-descripcion" name="descripcion"
                                    placeholder="Breve descripción del curso..." rows="3"
                                    style="width:100%; padding:10px; border:1px solid var(--borde); border-radius:8px; font-family:'Inter', sans-serif; resize:vertical;"></textarea>
                            </div>

                            <!-- Requisitos -->
                            <div class="form-group-modal modal-col-full">
                                <label for="cu-requisitos">Requisitos</label>
                                <textarea id="cu-requisitos" name="requisitos"
                                    placeholder="Requisitos previos para tomar el curso..." rows="2"
                                    style="width:100%; padding:10px; border:1px solid var(--borde); border-radius:8px; font-family:'Inter', sans-serif; resize:vertical;"></textarea>
                            </div>

                        </div>

                        <!-- ===== PIE DEL FORMULARIO ===== -->
                        <div class="modal-footer">
                            <button type="button" class="btn-modal-cancel"
                                id="btn-cancelar-modal-curso">Cancelar</button>
                            <button type="submit" class="btn-modal-submit">
                                <i class="ri-save-line"></i> Guardar Curso
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- ======================= MODAL: VER CURSO ======================= -->
<div class="modal-overlay" id="modal-ver-curso">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title-group">
                <span class="modal-title-icon"><i class="ri-book-open-line"></i></span>
                <h2 class="modal-title">Detalle del Curso</h2>
            </div>
            <button type="button" class="modal-close-btn" id="modal-close-ver-curso" title="Cerrar">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div class="modal-body" id="ver-curso-content">
            <div class="modal-section-label"><i class="ri-information-line"></i> Información del Curso</div>
            <div class="modal-grid">
                <div class="form-group-modal modal-col-full">
                    <label>Nombre</label>
                    <div id="vcu-nombre" style="padding:10px; background:#f5f5f5; border-radius:8px; font-size:14px;">—</div>
                </div>
                <div class="form-group-modal">
                    <label>Sede</label>
                    <div id="vcu-sede" style="padding:10px; background:#f5f5f5; border-radius:8px; font-size:14px;">—</div>
                </div>
                <div class="form-group-modal">
                    <label>Nivel</label>
                    <div id="vcu-nivel" style="padding:10px; background:#f5f5f5; border-radius:8px; font-size:14px;">—</div>
                </div>
                <div class="form-group-modal">
                    <label>Duración</label>
                    <div id="vcu-duracion" style="padding:10px; background:#f5f5f5; border-radius:8px; font-size:14px;">—</div>
                </div>
                <div class="form-group-modal">
                    <label>Horas Totales</label>
                    <div id="vcu-horas" style="padding:10px; background:#f5f5f5; border-radius:8px; font-size:14px;">—</div>
                </div>
                <div class="form-group-modal">
                    <label>Costo Base</label>
                    <div id="vcu-costo" style="padding:10px; background:#f5f5f5; border-radius:8px; font-size:14px;">—</div>
                </div>
                <div class="form-group-modal">
                    <label>Estatus</label>
                    <div id="vcu-estatus" style="padding:10px; background:#f5f5f5; border-radius:8px; font-size:14px;">—</div>
                </div>
                <div class="form-group-modal modal-col-full">
                    <label>Descripción</label>
                    <div id="vcu-descripcion" style="padding:10px; background:#f5f5f5; border-radius:8px; font-size:14px; min-height:60px;">—</div>
                </div>
                <div class="form-group-modal modal-col-full">
                    <label>Requisitos</label>
                    <div id="vcu-requisitos" style="padding:10px; background:#f5f5f5; border-radius:8px; font-size:14px; min-height:48px;">—</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" id="btn-cerrar-ver-curso">Cerrar</button>
        </div>
    </div>
</div>


        {{-- ===================== EXTRAESCOLARES ===================== --}}
        <script>
            window.PERMISOS_EXTRAESCOLARES = {
                ver:   {{ ($permisos->extracurriculares_ver  ?? false) ? 'true' : 'false' }},
                edit:  {{ ($permisos->extracurriculares_edit ?? false) ? 'true' : 'false' }},
                admin: {{ ($administrativo ?? false) ? 'true' : 'false' }}
            };
        </script>
        @include('admin.extraescolares')



        <section class="section-content" id="section-status" style="display: none;">
            <div class="section-header">
                <h1 class="section-title">Status General</h1>
                <button class="btn-secondary" id="btn-refresh-status" title="Actualizar datos">
                    <i class="ri-refresh-line"></i> Actualizar
                </button>
            </div>
            
            <!-- KPIs -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div class="card" style="padding: 20px;">
                    <p style="color: var(--texto-suave); font-size: 14px; margin-bottom: 8px;">Total Alumnos</p>
                    <h2 id="kpi-alumnos" style="font-size: 28px; font-weight: 700; color: var(--texto); margin: 0;">—</h2>
                </div>
                <div class="card" style="padding: 20px;">
                    <p style="color: var(--texto-suave); font-size: 14px; margin-bottom: 8px;">Extraescolares</p>
                    <h2 id="kpi-actividades" style="font-size: 28px; font-weight: 700; color: var(--texto); margin: 0;">—</h2>
                </div>
                <div class="card" style="padding: 20px;">
                    <p style="color: var(--texto-suave); font-size: 14px; margin-bottom: 8px;">Profesores</p>
                    <h2 id="kpi-profesores" style="font-size: 28px; font-weight: 700; color: var(--texto); margin: 0;">—</h2>
                </div>
                <div class="card" style="padding: 20px;">
                    <p style="color: var(--texto-suave); font-size: 14px; margin-bottom: 8px;">Crecimiento (Mes)</p>
                    <h2 id="kpi-crecimiento" style="font-size: 28px; font-weight: 700; margin: 0; color: var(--texto-suave);">—</h2>
                </div>
            </div>

            <!-- Feed de Actividad Reciente -->
            <div class="card" style="padding: 24px;">
                <h3 style="font-size: 16px; font-weight: 600; color: var(--texto); margin-bottom: 16px; border-bottom: 1px solid var(--borde); padding-bottom: 12px;">
                    <i class="ri-time-line"></i> Actividad Reciente
                </h3>
                <div id="lista-actividad-reciente">
                    <!-- Se llena dinámicamente con dashboardAdminStatus.js -->
                </div>
            </div>
        </section>

        {{-- ===================== PAGOS / FACTURAS ===================== --}}
        <script>
            window.PERMISOS_PAGOS = {
                ver:   {{ ($permisos->pagos_ver  ?? false) ? 'true' : 'false' }},
                edit:  {{ ($permisos->pagos_edit ?? false) ? 'true' : 'false' }},
                admin: {{ ($administrativo ?? false) ? 'true' : 'false' }}
            };
        </script>
        @include('admin.pagos')

        {{-- ===================== NIVELES ===================== --}}
        <script>
            window.PERMISOS_NIVELES = {
                ver:   {{ ($permisos->niveles_ver  ?? false) ? 'true' : 'false' }},
                edit:  {{ ($permisos->niveles_edit ?? false) ? 'true' : 'false' }},
                admin: {{ ($administrativo ?? false) ? 'true' : 'false' }}
            };
        </script>
        @include('admin.niveles')


        <!-- ======================= MODAL: MODIFICAR PUNTOS ======================= -->
        <div class="modal-overlay" id="modal-modificar-puntos">
            <div class="modal-box" style="max-width: 400px; width: 90%;">
                <div class="modal-header">
                    <div class="modal-title-group">
                        <span class="modal-title-icon"><i class="ri-award-line"></i></span>
                        <h2 class="modal-title">Modificar Puntuación</h2>
                    </div>
                    <button type="button" class="modal-close-btn" id="modal-close-puntos" title="Cerrar">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="modal-puntos-info">
                        <h3 id="puntos-modal-nombre">Nombre Alumno</h3>
                        <p style="color: var(--texto-suave); font-size: 14px; margin: 0;">Puntos actuales: <strong
                                id="puntos-modal-actuales" style="color: var(--naranja); font-size: 18px;">0</strong> /
                            2000</p>
                    </div>

                    <div class="form-group-modal">
                        <label for="puntos-modal-cantidad" class="puntos-modal-label">Cantidad de puntos:</label>
                        <input type="number" id="puntos-modal-cantidad" placeholder="Ej. 50" min="1" max="2000">
                    </div>

                    <div class="modal-puntos-actions">
                        <button type="button" id="btn-puntos-restar" class="btn-modal-action btn-modal-restar">
                            <i class="ri-subtract-line"></i> Quitar
                        </button>
                        <button type="button" id="btn-puntos-sumar" class="btn-modal-action btn-modal-sumar">
                            <i class="ri-add-line"></i> Añadir
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL AGREGAR ROL movido a admin/roles.blade.php -->
        <div class="modal-overlay" id="modal-agregar-rol">
            <div class="modal-box modal-box-large">
                <!-- Cabecera del modal -->
                <div class="modal-header">
                    <div class="modal-title-group">
                        <i class="ri-shield-keyhole-line modal-title-icon"></i>
                        <h2 class="modal-title">Agregar Nuevo Rol</h2>
                    </div>
                    <button class="modal-close-btn" id="modal-close-rol" title="Cerrar">
                        <i class="ri-close-line"></i>
                    </button>
                </div>

                <!-- Cuerpo del modal -->
                <div class="modal-body">
                    <form id="form-agregar-rol" novalidate>

                        <!-- Sección: Detalles del Rol -->
                        <div class="modal-section-label">
                            <i class="ri-information-line"></i> Detalles del Rol
                        </div>
                        <div class="modal-grid">
                            <div class="form-group-modal modal-col-full">
                                <label for="ro-nombre">Nombre del Rol <span
                                        style="color:var(--naranja)">*</span></label>
                                <input type="text" id="ro-nombre" name="ro_nombre" placeholder="Ej. Editor Académico"
                                    required>
                                <span class="error-msg-modal" id="err-ro-nombre"></span>
                            </div>
                            <div class="form-group-modal modal-col-full">
                                <label for="ro-descripcion">Descripción <span
                                        style="color:var(--naranja)">*</span></label>
                                <textarea id="ro-descripcion" name="ro_descripcion"
                                    placeholder="Explica qué funciones tendrá este rol..." rows="2"></textarea>
                                <span class="error-msg-modal" id="err-ro-descripcion"></span>
                            </div>
                            <div class="form-group-modal">
                                <label for="ro-tipo">Tipo de Acceso <span style="color:var(--naranja)">*</span></label>
                                <div class="form-dropdown" id="dropdown-ro-tipo" tabindex="0">
                                    <div class="form-select-trigger">
                                        <span class="selected-text" data-value="estandar">Estándar</span>
                                        <i class="ri-arrow-down-s-line"></i>
                                    </div>
                                    <div class="form-options-container">
                                        <div class="form-option selected" data-value="estandar">Estándar</div>
                                        <div class="form-option" data-value="admin">Administrador</div>
                                    </div>
                                    <input type="hidden" id="ro-tipo" name="ro_tipo" value="estandar">
                                </div>
                            </div>
                            <div class="form-group-modal">
                                <label for="ro-estatus">Estatus <span style="color:var(--naranja)">*</span></label>
                                <div class="form-dropdown" id="dropdown-ro-estatus" tabindex="0">
                                    <div class="form-select-trigger">
                                        <span class="selected-text" data-value="activo">Activo</span>
                                        <i class="ri-arrow-down-s-line"></i>
                                    </div>
                                    <div class="form-options-container">
                                        <div class="form-option selected" data-value="activo">Activo</div>
                                        <div class="form-option" data-value="inactivo">Inactivo</div>
                                    </div>
                                    <input type="hidden" id="ro-estatus" name="ro_estatus" value="activo">
                                </div>
                            </div>
                        </div>

                        <!-- Sección: Matriz de Permisos -->
                        <div class="modal-section-label">
                            <i class="ri-lock-password-line"></i> Matriz de Permisos
                        </div>

                        <div class="permissions-matrix-wrapper">
                            <table class="permissions-table">
                                <thead>
                                    <tr>
                                        <th>Módulo</th>
                                        <th class="text-center">Ver (Acceso)</th>
                                        <th class="text-center">Crear/Editar (Escritura)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $modulos = [
                                            ['id' => 'alumnos', 'nombre' => 'Alumnos'],
                                            ['id' => 'profesores', 'nombre' => 'Profesores'],
                                            ['id' => 'personal', 'nombre' => 'Personal'],
                                            ['id' => 'roles', 'nombre' => 'Roles'],
                                            ['id' => 'sedes', 'nombre' => 'Sedes'],
                                            ['id' => 'grupos', 'nombre' => 'Grupos'],
                                            ['id' => 'extraescolares', 'nombre' => 'Extraescolares'],
                                            ['id' => 'status', 'nombre' => 'Status'],
                                            ['id' => 'pagos', 'nombre' => 'Pagos'],
                                            ['id' => 'niveles', 'nombre' => 'Niveles'],
                                        ];
                                    @endphp
                                    @foreach($modulos as $mod)
                                        <tr>
                                            <td><strong>{{ $mod['nombre'] }}</strong></td>
                                            <td class="text-center">
                                                <label class="custom-checkbox-container">
                                                    <input type="checkbox" name="permiso_{{ $mod['id'] }}_ver" value="1">
                                                    <span class="checkmark"></span>
                                                </label>
                                            </td>
                                            <td class="text-center">
                                                @if($mod['id'] !== 'status')
                                                    <label class="custom-checkbox-container">
                                                        <input type="checkbox" name="permiso_{{ $mod['id'] }}_crear" value="1">
                                                        <span class="checkmark"></span>
                                                    </label>
                                                @else
                                                    <span style="color: var(--texto-suave); font-size: 0.8rem;">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- ===== PIE DEL FORMULARIO ===== -->
                        <div class="modal-footer">
                            <button type="button" class="btn-modal-cancel" id="btn-cancelar-modal-rol">Cancelar</button>
                            <button type="submit" class="btn-modal-submit">
                                <i class="ri-save-line"></i> Guardar Rol
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ===================== BIBLIOTECA VIRTUAL ===================== --}}
        @include('admin.biblioteca')

        <section class="section-content" id="section-opciones" style="display: none;">
            @include('admin.partials.configuracion')
        </section>
    </main>

    <!-- Sedes disponibles (dinámicas desde DB) -->
    <script>
        window.SEDES_MAP = {
            @foreach($sedes as $s)
                {{ $s->id_sede }}: "{{ $s->nombre }}",
            @endforeach
        };
    </script>
    <!-- JS General -->
    <script src="{{ asset('animaciones/admin/dashboardAdmin.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Personal -->
    <script src="{{ asset('animaciones/admin/dashboardAdminPersonal.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Alumnos -->
    <script src="{{ asset('animaciones/admin/dashboardAdminAlumnos.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Profesores -->
    <script src="{{ asset('animaciones/admin/dashboardAdminProfesores.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Sede -->
    <script src="{{ asset('animaciones/admin/dashboardAdminSede.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Grupos -->
    <script src="{{ asset('animaciones/admin/dashboardAdminGrupos.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Status -->
    <script src="{{ asset('animaciones/admin/dashboardAdminStatus.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Extraescolares -->
    <script src="{{ asset('animaciones/admin/dashboardAdminExtraescolares.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Facturas -->
    <script src="{{ asset('animaciones/admin/dashboardAdminFacturas.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Niveles -->
    <script src="{{ asset('animaciones/admin/dashboardAdminNiveles.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Roles -->
    <script src="{{ asset('animaciones/admin/dashboardAdminRoles.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Biblioteca (datos de prueba + vistas) -->
    <script src="{{ asset('animaciones/admin/dashboardAdminBibliotecaDatos.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('animaciones/admin/dashboardAdminBiblioteca.js') }}?v={{ time() }}"></script>
    <!-- JS Sección Configuración -->
    <script src="{{ asset('animaciones/admin/dashboardAdminPerfil.js') }}?v={{ time() }}"></script>

</body>

</html>
