<?php
/**
 * Archivo: header.php
 * Función: Cabecera común HTML + carga Tailwind + carga idioma + seguridad.
 * Seguridad: Validación sesión, escapado, encabezados, inicio sesión.
 * Se debe incluir al inicio de páginas protegidas.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/globals.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar sesión: si la página requiere login, se llamará a requireRole() en el script que incluya este header

// Cargar idioma seleccionado o por defecto
$langFile = __DIR__ . '/../lang/' . ( $_SESSION['lang'] ?? DEFAULT_LANG ) . '.php';
if (!file_exists($langFile)) {
    $langFile = __DIR__ . '/../lang/' . DEFAULT_LANG . '.php';
}
$lang = include $langFile;

// Función de escape HTML para mostrar texto seguro
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Tema claro/oscuro, se controla con localStorage en JS
$theme = $_SESSION['theme'] ?? 'light';

?><!DOCTYPE html>
<html lang="<?php echo e($_SESSION['lang'] ?? DEFAULT_LANG); ?>">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo e($lang['app_title'] ?? 'CRM'); ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Ajustes básicos para tema claro/oscuro */
        body.light {
            background-color: #f9fafb;
            color: #111827;
        }
        body.dark {
            background-color: #111827;
            color: #f9fafb;
        }
    </style>
    <script>
        // Aplicar tema según localStorage o default 'light'
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.className = savedTheme;
        document.body.className = savedTheme;
    </script>
</head>
<body>
<header class="bg-blue-700 text-white p-4 flex justify-between items-center">
    <h1 class="text-xl font-bold"><?php echo e($lang['app_title'] ?? 'CRM'); ?></h1>
    <nav>
        <ul class="flex gap-4">
            <li><a href="/pages/dashboard.php" class="hover:underline"><?php echo e($lang['nav_dashboard'] ?? 'Dashboard'); ?></a></li>
            <li><a href="/pages/clientes.php" class="hover:underline"><?php echo e($lang['nav_clients'] ?? 'Clientes'); ?></a></li>
            <li><a href="/pages/productos.php" class="hover:underline"><?php echo e($lang['nav_products'] ?? 'Productos'); ?></a></li>
            <li><a href="/pages/ventas.php" class="hover:underline"><?php echo e($lang['nav_sales'] ?? 'Ventas'); ?></a></li>
            <li><a href="/pages/reportes.php" class="hover:underline"><?php echo e($lang['nav_reports'] ?? 'Reportes'); ?></a></li>
            <li><a href="/pages/configuracion.php" class="hover:underline"><?php echo e($lang['nav_settings'] ?? 'Configuración'); ?></a></li>
            <li><a href="/pages/logout.php" class="hover:underline"><?php echo e($lang['nav_logout'] ?? 'Salir'); ?></a></li>
        </ul>
    </nav>
</header>
<main class="p-6 max-w-7xl mx-auto">
