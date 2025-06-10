<?php
/**
 * Archivo: dashboard.php
 * Función: Página principal del sistema con resumen y estadísticas.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Validar que el usuario esté logueado y con rol permitido (ejemplo: admin o user)
requireRole(['admin', 'user']);

// Obtenemos datos para mostrar en el dashboard

// Total clientes
$stmt = $pdo->query("SELECT COUNT(*) FROM clientes");
$totalClientes = (int) $stmt->fetchColumn();

// Total productos
$stmt = $pdo->query("SELECT COUNT(*) FROM productos");
$totalProductos = (int) $stmt->fetchColumn();

// Total ventas
$stmt = $pdo->query("SELECT COUNT(*) FROM ventas");
$totalVentas = (int) $stmt->fetchColumn();

?>

<h2 class="text-2xl font-bold mb-6">Bienvenido, <?php echo e($_SESSION['username'] ?? 'Usuario'); ?>!</h2>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded shadow text-center">
        <h3 class="text-lg font-semibold mb-2">Total Clientes</h3>
        <p class="text-3xl font-bold text-blue-700"><?php echo $totalClientes; ?></p>
    </div>
    <div class="bg-white p-6 rounded shadow text-center">
        <h3 class="text-lg font-semibold mb-2">Total Productos</h3>
        <p class="text-3xl font-bold text-green-700"><?php echo $totalProductos; ?></p>
    </div>
    <div class="bg-white p-6 rounded shadow text-center">
        <h3 class="text-lg font-semibold mb-2">Total Ventas</h3>
        <p class="text-3xl font-bold text-purple-700"><?php echo $totalVentas; ?></p>
    </div>
</div>

<section class="bg-white p-6 rounded shadow">
    <h3 class="text-xl font-semibold mb-4">Información de usuario</h3>
    <ul>
        <li><strong>Usuario:</strong> <?php echo e($_SESSION['username']); ?></li>
        <li><strong>Rol:</strong> <?php echo e($_SESSION['role']); ?></li>
        <li><strong>Sesión iniciada:</strong> <?php echo e(date('d/m/Y H:i:s', $_SESSION['login_time'] ?? time())); ?></li>
    </ul>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
