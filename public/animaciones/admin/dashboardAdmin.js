// ==============================================
//  dashboardAdmin.js — Interactividad General
//  Sidebar, Navegación y Menú de Perfil
//  EGAU Chess | AMAAC
// ==============================================

// ── Toast global EGAU ──
window.egauAlert = function (mensaje, tipo = 'success') {
    const iconos = {
        success: 'ri-checkbox-circle-line',
        error: 'ri-error-warning-line',
        warning: 'ri-alert-line'
    };
    let toast = document.getElementById('egau-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'egau-toast';
        document.body.appendChild(toast);
    }
    toast.className = `egau-toast egau-toast--${tipo}`;
    toast.innerHTML = `<i class="${iconos[tipo] || iconos.success}"></i><span>${mensaje}</span>`;
    requestAnimationFrame(() => {
        requestAnimationFrame(() => toast.classList.add('visible'));
    });
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
        toast.classList.remove('visible');
    }, 3500);
};

document.addEventListener('DOMContentLoaded', () => {

    // ---- Toggle del sidebar (Móvil y Escritorio) ----
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('open');
            } else {
                sidebar.classList.toggle('collapsed');
                if (mainContent) {
                    mainContent.classList.toggle('sidebar-hidden');
                }
            }
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 &&
                !sidebar.contains(e.target) &&
                !menuToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ---- Navegación: resaltar sección activa y cambiar contenido ----
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();

            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            item.classList.add('active');

            document.querySelectorAll('.section-content').forEach(sec => {
                sec.style.display = 'none';
            });

            const sectionId = item.getAttribute('data-section');
            const targetSection = document.getElementById('section-' + sectionId);
            if (targetSection) {
                targetSection.style.display = 'block';
            }

            if (window.innerWidth <= 768 && sidebar) {
                sidebar.classList.remove('open');
            }
        });
    });

    // ---- Menú de Perfil (Cuenta Administrador) ----
    const profileMenu = document.getElementById('profile-menu');

    if (profileMenu) {
        const profileTrigger = profileMenu.querySelector('.profile-trigger');

        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileMenu.classList.toggle('open');
        });

        document.addEventListener('click', () => {
            profileMenu.classList.remove('open');
        });

        const btnConfigPerfil = document.getElementById('btn-config-perfil');
        if (btnConfigPerfil) {
            btnConfigPerfil.addEventListener('click', () => {
                const navOpciones = document.querySelector('.nav-footer-item[data-section="opciones"]');
                if (navOpciones) {
                    navOpciones.click();
                }
            });
        }

        // Botón Cerrar Sesión
        document.getElementById('btn-logout')?.addEventListener('click', () => {
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('/logout', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json' }
            }).then(() => window.location.href = '/login');
        });
    }

    // ---- Datos reales del personal en sesión ----
    const esProfesor = window.ES_PROFESOR || false;
    const perfilUrl = esProfesor ? '/profesor/perfil' : '/personal/perfil';
    fetch(perfilUrl, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
        .then(r => r.json())
        .then(d => {
            if (d.error) return;

            const iniciales = ((d.nombre?.[0] || '') + (d.apellido_p?.[0] || '')).toUpperCase();

            const navNombre = document.querySelector('.profile-trigger .admin-name');
            const navAvatar = document.querySelector('.profile-trigger .avatar');
            if (navNombre) navNombre.textContent = d.nombre || 'Personal';
            if (navAvatar) navAvatar.textContent = iniciales;

            const ddNombre = document.querySelector('.profile-dropdown .profile-name');
            const ddEmail = document.querySelector('.profile-dropdown .profile-email');
            if (ddNombre) ddNombre.textContent = [d.nombre, d.apellido_p, d.apellido_m]
                .filter(Boolean).join(' ');
            if (ddEmail) ddEmail.textContent = d.email || '—';

            window._perfilPersonal = d;
        })
        .catch(err => console.error('Error cargando perfil:', err))
        .finally(() => {
            const loading = document.getElementById('loading-screen');
            if (loading) {
                loading.style.opacity = '0';
                setTimeout(() => loading.style.display = 'none', 400);
            }
        });

    // ---- Handler global form-dropdowns ----
    function cerrarTodosDropdowns() {
        document.querySelectorAll('.form-dropdown').forEach(d => {
            d.classList.remove('open');
            const c = d.querySelector('.form-options-container');
            if (c) c.style.display = 'none';
        });
    }

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.form-select-trigger');
        if (trigger) {
            e.stopImmediatePropagation();
            const dropdown = trigger.closest('.form-dropdown');
            if (!dropdown) return;
            const container = dropdown.querySelector('.form-options-container');
            const estaAbierto = dropdown.classList.contains('open');

            cerrarTodosDropdowns();

            if (!estaAbierto && container) {
                dropdown.classList.add('open');
                container.style.cssText = `
                display: block !important;
                position: absolute;
                top: calc(100% + 4px);
                left: 0;
                right: 0;
                z-index: 99999;
                background: #fff;
                border: 1.5px solid #ebebeb;
                border-radius: 10px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.1);
                max-height: 200px;
                overflow-y: auto;
            `;
            }
            return;
        }

        const option = e.target.closest('.form-option');
        if (option) {
            e.stopImmediatePropagation();
            const dropdown = option.closest('.form-dropdown');
            if (!dropdown) return;
            dropdown.querySelectorAll('.form-option').forEach(o => o.classList.remove('selected'));
            option.classList.add('selected');
            const selectedText = dropdown.querySelector('.selected-text');
            const hiddenInput = dropdown.querySelector('input[type="hidden"]');
            if (selectedText) {
                selectedText.textContent = option.textContent.trim();
                selectedText.setAttribute('data-value', option.getAttribute('data-value'));
            }
            if (hiddenInput) {
                hiddenInput.value = option.getAttribute('data-value');
                const err = document.getElementById(`err-${hiddenInput.id}`);
                if (err) err.textContent = '';
                dropdown.classList.remove('input-error');
            }
            cerrarTodosDropdowns();
            return;
        }

        cerrarTodosDropdowns();
    });
});