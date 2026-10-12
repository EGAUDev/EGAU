// ==============================================
//  dashboardAdminBibliotecaDatos.js — Datos de prueba
//  Biblioteca Virtual (SCRUM-18)
//  EGAU Chess | AMAAC
//
//  Mientras no existan las tablas de la biblioteca (SCRUM-10/12),
//  las vistas leen de aquí. Al tener la API, se reemplaza este
//  archivo por fetch() y dashboardAdminBiblioteca.js no cambia.
// ==============================================

window.BIBLIOTECA_DATOS = (() => {

    // Mismos nombres y umbrales que NivelesController
    const NIVELES = [
        { nombre: 'Peón',    icono: '♟', puntos: 0,    clase: 'bib-nivel-peon' },
        { nombre: 'Caballo', icono: '♞', puntos: 150,  clase: 'bib-nivel-caballo' },
        { nombre: 'Alfil',   icono: '♝', puntos: 400,  clase: 'bib-nivel-alfil' },
        { nombre: 'Torre',   icono: '♜', puntos: 800,  clase: 'bib-nivel-torre' },
        { nombre: 'Reina',   icono: '♛', puntos: 1500, clase: 'bib-nivel-reina' },
        { nombre: 'Rey',     icono: '♚', puntos: 3000, clase: 'bib-nivel-rey' },
    ];

    const TIPOS = [
        { nombre: 'Libro',     icono: 'ri-book-open-line',   clase: 'bib-tipo-libro' },
        { nombre: 'Partida',   icono: 'ri-sword-line',       clase: 'bib-tipo-partida' },
        { nombre: 'Video',     icono: 'ri-video-line',       clase: 'bib-tipo-video' },
        { nombre: 'Ejercicio', icono: 'ri-file-text-line',   clase: 'bib-tipo-ejercicio' },
    ];

    const PORTADAS = ['bib-portada-1', 'bib-portada-2', 'bib-portada-3', 'bib-portada-4', 'bib-portada-5', 'bib-portada-6'];

    const documentos = [
        {
            id: 1, titulo: 'Aperturas para el Jugador Moderno', creador: 'GM Alejandro Pérez',
            tema: 'Aperturas, Teoría',
            descripcion: 'Manual completo de aperturas modernas incluyendo sistemas principales con blancas y negras. Análisis detallado de variantes con ejemplos de partidas magistrales.',
            fecha: '2023-03-15', tipo: 'Libro', formato: 'PDF', idioma: 'Español', nivel: 'Alfil',
            palabrasClave: ['apertura', 'gambito', 'siciliana', 'española', 'defensa india'],
            tamano: '4.2 MB', estatus: 'Activo', portada: 'bib-portada-1',
        },
        {
            id: 2, titulo: 'Tácticas Combinativas: 300 Ejercicios', creador: 'MI Rodrigo Salinas',
            tema: 'Táctica, Combinaciones',
            descripcion: 'Colección de 300 ejercicios de táctica clasificados por motivo: clavada, horquilla, descubierta, ataque doble y jaque perpetuo.',
            fecha: '2022-09-01', tipo: 'Ejercicio', formato: 'PDF', idioma: 'Español', nivel: 'Caballo',
            palabrasClave: ['táctica', 'combinación', 'ejercicios', 'horquilla', 'clavada'],
            tamano: '2.8 MB', estatus: 'Activo', portada: 'bib-portada-2',
        },
        {
            id: 3, titulo: 'Finales de Torre: Técnica Avanzada', creador: 'GM Valentina Cruz',
            tema: 'Finales, Torre',
            descripcion: 'Estudio sistemático de los finales de torre: posición de Lucena, defensa de Philidor, torres activas y pasivas. Con 50 partidas anotadas.',
            fecha: '2023-07-20', tipo: 'Libro', formato: 'PDF', idioma: 'Español', nivel: 'Torre',
            palabrasClave: ['final', 'torre', 'Lucena', 'Philidor', 'técnica'],
            tamano: '5.1 MB', estatus: 'Activo', portada: 'bib-portada-3',
        },
        {
            id: 4, titulo: 'Memorias de un Gran Maestro', creador: 'GM Héctor Montoya',
            tema: 'Historia, Partidas magistrales',
            descripcion: 'Autobiografía del GM Montoya con análisis de sus 50 mejores partidas en torneos internacionales. Una obra imprescindible para el ajedrecista culto.',
            fecha: '2021-11-12', tipo: 'Libro', formato: 'PDF', idioma: 'Español', nivel: 'Reina',
            palabrasClave: ['maestro', 'historia', 'torneos', 'autobiografía', 'análisis'],
            tamano: '8.3 MB', estatus: 'Activo', portada: 'bib-portada-4',
        },
        {
            id: 5, titulo: 'Introducción al Ajedrez para Principiantes', creador: 'EGAU CHIES',
            tema: 'Básico, Reglas',
            descripcion: 'Video introductorio que explica las reglas del ajedrez, movimiento de las piezas y conceptos básicos como el enroque y la captura al paso.',
            fecha: '2024-01-10', tipo: 'Video', formato: 'MP4', idioma: 'Español', nivel: 'Peón',
            palabrasClave: ['principiante', 'reglas', 'piezas', 'básico', 'introducción'],
            tamano: '320 MB', estatus: 'Activo', portada: 'bib-portada-5',
        },
        {
            id: 6, titulo: 'El Tenedor: Ejercicios de Horquilla', creador: 'MI Sandra Fuentes',
            tema: 'Táctica, Horquilla',
            descripcion: 'Hoja de ejercicios con 40 posiciones de horquilla (tenedor), ordenadas por dificultad, para practicar este motivo táctico fundamental.',
            fecha: '2023-05-05', tipo: 'Ejercicio', formato: 'PDF', idioma: 'Español', nivel: 'Peón',
            palabrasClave: ['tenedor', 'horquilla', 'táctica', 'ejercicios'],
            tamano: '1.1 MB', estatus: 'Activo', portada: 'bib-portada-6',
        },
        {
            id: 7, titulo: 'El Ataque Indio de Rey: Repertorio con Blancas', creador: 'FM Carlos Ríos',
            tema: "Aperturas, King's Indian Attack",
            descripcion: 'Repertorio completo basado en el Ataque Indio de Rey para usar con blancas contra cualquier respuesta de las negras. Incluye planes típicos y variantes.',
            fecha: '2023-10-30', tipo: 'Libro', formato: 'PDF', idioma: 'Español', nivel: 'Alfil',
            palabrasClave: ['KIA', 'ataque indio', 'repertorio', 'blancas', 'apertura'],
            tamano: '3.7 MB', estatus: 'Activo', portada: 'bib-portada-1',
        },
        {
            id: 8, titulo: 'Defensa Siciliana: De Najdorf a Scheveningen', creador: 'GM Valentina Cruz',
            tema: 'Aperturas, Defensa Siciliana',
            descripcion: 'Análisis profundo de las variantes más populares de la Defensa Siciliana. Desde la Najdorf hasta la Scheveningen, con análisis de motor.',
            fecha: '2024-02-18', tipo: 'Libro', formato: 'PDF', idioma: 'Español', nivel: 'Torre',
            palabrasClave: ['siciliana', 'najdorf', 'scheveningen', 'dragón', 'apertura'],
            tamano: '6.4 MB', estatus: 'Activo', portada: 'bib-portada-2',
        },
        {
            id: 9, titulo: 'Kasparov: Las Mejores Partidas Comentadas', creador: 'GM Alejandro Pérez',
            tema: 'Partidas magistrales, Análisis',
            descripcion: 'Selección de 20 partidas de Garri Kasparov con comentarios detallados en notación algebraica. Incluye variantes y evaluaciones de motor.',
            fecha: '2022-06-14', tipo: 'Partida', formato: 'PGN', idioma: 'Español', nivel: 'Reina',
            palabrasClave: ['Kasparov', 'análisis', 'magistral', 'comentado', 'historia'],
            tamano: '0.9 MB', estatus: 'Activo', portada: 'bib-portada-3',
        },
        {
            id: 10, titulo: 'Problemas de Mate en 2 Movimientos', creador: 'MI Rodrigo Salinas',
            tema: 'Problemas, Mate',
            descripcion: 'Compilación de 60 problemas de mate en 2 movimientos, ordenados por dificultad, ideales para el entrenamiento diario de la visión táctica.',
            fecha: '2023-08-22', tipo: 'Ejercicio', formato: 'PDF', idioma: 'Español', nivel: 'Caballo',
            palabrasClave: ['mate', 'problemas', 'dos movimientos', 'táctica', 'puzzle'],
            tamano: '1.5 MB', estatus: 'Activo', portada: 'bib-portada-4',
        },
    ];

    // Jugadas en notación algebraica española (R, D, T, A, C)
    const partidas = [
        {
            id: 1, blancas: 'Paul Morphy', negras: 'Duque de Brunswick y Conde Isouard', resultado: '1-0',
            apertura: 'Defensa Philidor', eco: 'C41', nivel: 'Peón', fecha: '1858-11-02',
            evento: 'Partida de la Ópera, París 1858',
            movimientos: ['e4', 'e5', 'Cf3', 'd6', 'd4', 'Ag4', 'dxe5', 'Axf3', 'Dxf3', 'dxe5',
                'Ac4', 'Cf6', 'Db3', 'De7', 'Cc3', 'c6', 'Ag5', 'b5', 'Cxb5', 'cxb5',
                'Axb5+', 'Cbd7', '0-0-0', 'Td8', 'Txd7', 'Txd7', 'Td1', 'De6', 'Axd7+', 'Cxd7',
                'Db8+', 'Cxb8', 'Td8#'],
        },
        {
            id: 2, blancas: 'Magnus Carlsen', negras: 'Fabiano Caruana', resultado: '½-½',
            apertura: 'Defensa Siciliana', eco: 'B33', nivel: 'Rey', fecha: '2018-11-26',
            evento: 'Campeonato Mundial, Londres 2018',
            movimientos: ['e4', 'c5', 'Cf3', 'Cc6', 'd4', 'cxd4', 'Cxd4', 'Cf6', 'Cc3', 'e5',
                'Cdb5', 'd6', 'Ag5', 'a6', 'Ca3', 'b5', 'Cd5', 'De7'],
        },
        {
            id: 3, blancas: 'Anatoli Karpov', negras: 'Garri Kasparov', resultado: '0-1',
            apertura: 'Defensa Nimzo-India', eco: 'E21', nivel: 'Torre', fecha: '1985-11-09',
            evento: 'Campeonato Mundial, Moscú 1985',
            movimientos: ['d4', 'Cf6', 'c4', 'e6', 'Cc3', 'Ab4', 'Cf3', '0-0', 'Ag5', 'c5',
                'e3', 'cxd4', 'exd4', 'h6', 'Ah4', 'd5'],
        },
        {
            id: 4, blancas: 'Bobby Fischer', negras: 'Boris Spassky', resultado: '1-0',
            apertura: 'Defensa Siciliana (variante Najdorf)', eco: 'B97', nivel: 'Torre', fecha: '1972-08-10',
            evento: 'Campeonato Mundial, Reikiavik 1972',
            movimientos: ['e4', 'c5', 'Cf3', 'd6', 'd4', 'cxd4', 'Cxd4', 'Cf6', 'Cc3', 'a6',
                'Ag5', 'e6', 'f4', 'Db6', 'Dd2', 'Dxb2'],
        },
        {
            id: 5, blancas: 'Vishy Anand', negras: 'Vladimir Kramnik', resultado: '1-0',
            apertura: 'Gambito de Dama Rehusado', eco: 'D43', nivel: 'Alfil', fecha: '2008-10-29',
            evento: 'Campeonato Mundial, Bonn 2008',
            movimientos: ['d4', 'd5', 'c4', 'c6', 'Cf3', 'Cf6', 'Cc3', 'e6', 'Ag5', 'dxc4',
                'e4', 'b5', 'e5', 'h6', 'Ah4', 'g5'],
        },
        {
            id: 6, blancas: 'José Raúl Capablanca', negras: 'Emanuel Lasker', resultado: '1-0',
            apertura: 'Gambito de Dama Rehusado', eco: 'D35', nivel: 'Caballo', fecha: '1921-03-28',
            evento: 'Campeonato Mundial, La Habana 1921',
            movimientos: ['d4', 'd5', 'c4', 'e6', 'Cc3', 'Cf6', 'Cf3', 'Ae7', 'Ag5', '0-0',
                'e3', 'Cbd7', 'Tc1', 'c6'],
        },
    ];

    // Valores iniciales de "Mis guardados" e "Historial" (como en el Figma)
    const guardadosIniciales = [1, 3, 7];
    const historialInicial = [
        { id: 5, fechaConsulta: '2026-10-07' },
        { id: 2, fechaConsulta: '2026-10-05' },
        { id: 8, fechaConsulta: '2026-10-03' },
        { id: 1, fechaConsulta: '2026-09-28' },
    ];

    return { NIVELES, TIPOS, PORTADAS, documentos, partidas, guardadosIniciales, historialInicial };
})();
