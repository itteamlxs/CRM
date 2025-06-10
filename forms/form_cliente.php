<?php
/**
 * Archivo: forms/form_cliente.php
 * Función: Formulario HTML para crear o editar clientes.
 * Seguridad: CSRF token, validación frontend, roles autorizados.
 * Requiere: Sesión activa, rol administrador o vendedor.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireRole(['admin', 'vendedor']);

$pdo = getPDO();

// Verificar si es edición (ID en query string)
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$cliente = [
    'nombre' => '',
    'email' => '',
    'telefono' => '',
    'direccion' => '',
    'estado' => 'activo'
];

if ($id) {
    // Obtener datos del cliente para editar
    $stmt = $pdo->prepare("SELECT nombre, email, telefono, direccion, estado FROM clientes WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $cliente_db = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cliente_db) {
        $cliente = $cliente_db;
    } else {
        // Cliente no encontrado, redirigir
        header('Location: /pages/clientes/index.php?error=not_found');
        exit;
    }
}

// Generar token CSRF para el formulario
$csrf_token = generate_csrf_token();

// Cargar idioma
$langFile = __DIR__ . '/../lang/' . ($_SESSION['lang'] ?? 'es') . '.php';
if (!file_exists($langFile)) {
    $langFile = __DIR__ . '/../lang/es.php';
}
$lang = include $langFile;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-6 max-w-2xl">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">
            <?php if ($id): ?>
                <?php echo htmlspecialchars($lang['edit_client'] ?? 'Editar Cliente', ENT_QUOTES, 'UTF-8'); ?>
            <?php else: ?>
                <?php echo htmlspecialchars($lang['new_client'] ?? 'Nuevo Cliente', ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </h1>

        <form method="POST" action="<?php echo url('forms/procesar_cliente.php'); ?>" novalidate class="space-y-4">
            <!-- Token CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            
            <!-- ID para edición -->
            <?php if ($id): ?>
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <?php endif; ?>

            <!-- Nombre -->
            <div>
                <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">
                    <?php echo htmlspecialchars($lang['client_name'] ?? 'Nombre', ENT_QUOTES, 'UTF-8'); ?> *
                </label>
                <input 
                    type="text" 
                    name="nombre" 
                    id="nombre" 
                    required 
                    maxlength="100"
                    value="<?php echo htmlspecialchars($cliente['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="<?php echo htmlspecialchars($lang['client_name_placeholder'] ?? 'Nombre completo del cliente', ENT_QUOTES, 'UTF-8'); ?>"
                />
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    <?php echo htmlspecialchars($lang['client_email'] ?? 'Email', ENT_QUOTES, 'UTF-8'); ?> *
                </label>
                <input 
                    type="email" 
                    name="email" 
                    id="email" 
                    required 
                    maxlength="100"
                    value="<?php echo htmlspecialchars($cliente['email'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="cliente@ejemplo.com"
                />
            </div>

            <!-- Teléfono -->
            <div>
                <label for="telefono" class="block text-sm font-medium text-gray-700 mb-1">
                    <?php echo htmlspecialchars($lang['client_phone'] ?? 'Teléfono', ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <input 
                    type="tel" 
                    name="telefono" 
                    id="telefono" 
                    maxlength="30"
                    value="<?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="+1 (555) 123-4567"
                />
            </div>

            <!-- Dirección -->
            <div>
                <label for="direccion" class="block text-sm font-medium text-gray-700 mb-1">
                    <?php echo htmlspecialchars($lang['client_address'] ?? 'Dirección', ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <textarea 
                    name="direccion" 
                    id="direccion" 
                    rows="3"
                    maxlength="255"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="<?php echo htmlspecialchars($lang['client_address_placeholder'] ?? 'Dirección completa del cliente', ENT_QUOTES, 'UTF-8'); ?>"
                ><?php echo htmlspecialchars($cliente['direccion'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <!-- Estado -->
            <div>
                <label for="estado" class="block text-sm font-medium text-gray-700 mb-1">
                    <?php echo htmlspecialchars($lang['client_status'] ?? 'Estado', ENT_QUOTES, 'UTF-8'); ?>
                </label>
                <select 
                    name="estado" 
                    id="estado" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                    <option value="activo" <?php echo $cliente['estado'] === 'activo' ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lang['status_active'] ?? 'Activo', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <option value="inactivo" <?php echo $cliente['estado'] === 'inactivo' ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lang['status_inactive'] ?? 'Inactivo', ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                </select>
            </div>

            <!-- Botones -->
            <div class="flex justify-end space-x-4 pt-4">
                <a 
                    href="<?php echo url('pages/clientes/index.php'); ?>" 
                    class="px-4 py-2 text-gray-600 bg-gray-200 rounded-md hover:bg-gray-300 transition-colors"
                >
                    <?php echo htmlspecialchars($lang['cancel'] ?? 'Cancelar', ENT_QUOTES, 'UTF-8'); ?>
                </a>
                
                <button 
                    type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    <?php if ($id): ?>
                        <?php echo htmlspecialchars($lang['update_client'] ?? 'Actualizar Cliente', ENT_QUOTES, 'UTF-8'); ?>
                    <?php else: ?>
                        <?php echo htmlspecialchars($lang['create_client'] ?? 'Crear Cliente', ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Validación básica del formulario
document.querySelector('form').addEventListener('submit', function(e) {
    const nombre = document.getElementById('nombre').value.trim();
    const email = document.getElementById('email').value.trim();
    
    if (!nombre) {
        alert('<?php echo htmlspecialchars($lang['error_name_required'] ?? 'El nombre es obligatorio', ENT_QUOTES, 'UTF-8'); ?>');
        e.preventDefault();
        return;
    }
    
    if (!email || !email.includes('@')) {
        alert('<?php echo htmlspecialchars($lang['error_email_invalid'] ?? 'Email inválido', ENT_QUOTES, 'UTF-8'); ?>');
        e.preventDefault();
        return;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>