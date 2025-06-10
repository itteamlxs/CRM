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
require_once __DIR__ . '/functions.php';

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar idioma seleccionado o por defecto
$langFile = __DIR__ . '/../lang/' . ($_SESSION['lang'] ?? DEFAULT_LANG) . '.php';
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
    
    <!-- Configuración de Tailwind para tema oscuro -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
    
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
        
        /* Transiciones suaves */
        * {
            transition: background-color 0.2s ease, color 0.2s ease;
        }
    </style>
    
    <script>
        // Aplicar tema según localStorage o default 'light'
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
            document.body.className = savedTheme;
        });
        
        // Función para cambiar tema
        function toggleTheme() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            localStorage.setItem('theme', newTheme);
            document.documentElement.classList.toggle('dark', newTheme === 'dark');
            document.body.className = newTheme;
        }
    </script>
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100">
    
    <!-- Incluir navegación si hay sesión activa -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <?php include __DIR__ . '/nav.php'; ?>
    <?php endif; ?>
    
    <!-- Contenedor principal -->
    <main class="<?php echo isset($_SESSION['user_id']) ? 'pt-4' : ''; ?>">
        
        <!-- Mensajes flash (éxito, error, etc.) -->
        <?php if (isset($_GET['success'])): ?>
            <div class="max-w-7xl mx-auto px-4 mb-4">
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm"><?php echo e($_GET['success']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="max-w-7xl mx-auto px-4 mb-4">
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm"><?php echo e($_GET['error']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>