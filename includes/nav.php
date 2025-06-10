<?php
/**
 * Archivo: nav.php
 * Función: Navegación común del sistema con permisos por rol.
 * Seguridad: Validación de sesión activa, mostrar opciones según rol.
 */

// Solo mostrar navegación si hay sesión activa
if (!isset($_SESSION['user_id'])) {
    return;
}

$userRole = $_SESSION['role'] ?? '';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

?>
<nav class="bg-blue-800 shadow-lg">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
            <div class="flex space-x-8">
                <!-- Logo/Título -->
                <div class="flex-shrink-0 flex items-center">
                    <h1 class="text-xl font-bold text-white">
                        <?php echo htmlspecialchars($lang['app_title'] ?? 'CRM', ENT_QUOTES, 'UTF-8'); ?>
                    </h1>
                </div>
                
                <!-- Menú principal -->
                <div class="hidden md:flex items-center space-x-4">
                    <a href="/pages/dashboard.php" 
                       class="<?php echo $currentPage === 'dashboard' ? 'bg-blue-900' : 'hover:bg-blue-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium">
                        <?php echo htmlspecialchars($lang['nav_dashboard'] ?? 'Dashboard', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    
                    <a href="/pages/clientes/index.php" 
                       class="<?php echo $currentPage === 'index' && strpos($_SERVER['REQUEST_URI'], 'clientes') ? 'bg-blue-900' : 'hover:bg-blue-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium">
                        <?php echo htmlspecialchars($lang['nav_clients'] ?? 'Clientes', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    
                    <a href="/pages/productos/index.php" 
                       class="<?php echo $currentPage === 'index' && strpos($_SERVER['REQUEST_URI'], 'productos') ? 'bg-blue-900' : 'hover:bg-blue-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium">
                        <?php echo htmlspecialchars($lang['nav_products'] ?? 'Productos', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    
                    <a href="/pages/cotizaciones/index.php" 
                       class="<?php echo $currentPage === 'index' && strpos($_SERVER['REQUEST_URI'], 'cotizaciones') ? 'bg-blue-900' : 'hover:bg-blue-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium">
                        <?php echo htmlspecialchars($lang['nav_quotes'] ?? 'Cotizaciones', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    
                    <a href="/pages/ventas/index.php" 
                       class="<?php echo $currentPage === 'index' && strpos($_SERVER['REQUEST_URI'], 'ventas') ? 'bg-blue-900' : 'hover:bg-blue-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium">
                        <?php echo htmlspecialchars($lang['nav_sales'] ?? 'Ventas', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    
                    <a href="/pages/reportes.php" 
                       class="<?php echo $currentPage === 'reportes' ? 'bg-blue-900' : 'hover:bg-blue-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium">
                        <?php echo htmlspecialchars($lang['nav_reports'] ?? 'Reportes', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Menú usuario -->
            <div class="flex items-center space-x-4">
                <!-- Solo admin puede ver configuración y usuarios -->
                <?php if ($userRole === 'administrador'): ?>
                    <a href="/pages/configuracion.php" 
                       class="<?php echo $currentPage === 'configuracion' ? 'bg-blue-900' : 'hover:bg-blue-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium">
                        <?php echo htmlspecialchars($lang['nav_settings'] ?? 'Configuración', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    
                    <a href="/pages/usuarios/index.php" 
                       class="<?php echo $currentPage === 'index' && strpos($_SERVER['REQUEST_URI'], 'usuarios') ? 'bg-blue-900' : 'hover:bg-blue-700'; ?> text-white px-3 py-2 rounded-md text-sm font-medium">
                        <?php echo htmlspecialchars($lang['nav_users'] ?? 'Usuarios', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endif; ?>
                
                <!-- Info usuario -->
                <div class="text-white text-sm">
                    <span class="hidden lg:inline">
                        <?php echo htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        <span class="text-blue-300">(<?php echo htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8'); ?>)</span>
                    </span>
                </div>
                
                <!-- Logout -->
                <a href="/pages/logout.php" 
                   class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium">
                    <?php echo htmlspecialchars($lang['nav_logout'] ?? 'Salir', ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Menú móvil (básico) -->
    <div class="md:hidden">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <a href="/pages/dashboard.php" class="text-white hover:bg-blue-700 block px-3 py-2 rounded-md text-base font-medium">
                <?php echo htmlspecialchars($lang['nav_dashboard'] ?? 'Dashboard', ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href="/pages/clientes/index.php" class="text-white hover:bg-blue-700 block px-3 py-2 rounded-md text-base font-medium">
                <?php echo htmlspecialchars($lang['nav_clients'] ?? 'Clientes', ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <!-- Agregar más enlaces según sea necesario -->
        </div>
    </div>
</nav>