<?php
/**
 * Archivo: login.php
 * Función: Formulario de acceso al sistema con seguridad.
 * No requiere sesión.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/globals.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header('Location: ' . url('pages/dashboard.php'));
    exit;
}

$langFile = __DIR__ . '/../lang/' . ( $_SESSION['lang'] ?? DEFAULT_LANG ) . '.php';
if (!file_exists($langFile)) {
    $langFile = __DIR__ . '/../lang/' . DEFAULT_LANG . '.php';
}
$lang = include $langFile;

$csrf_token = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['lang'] ?? DEFAULT_LANG, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8" />
    <title><?php echo htmlspecialchars($lang['login_title'] ?? 'Login', ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <form action="<?php echo url('forms/procesar_login.php'); ?>" method="POST" class="bg-white p-8 rounded shadow-md w-full max-w-sm">
        <h2 class="text-xl mb-6"><?php echo htmlspecialchars($lang['login_title'], ENT_QUOTES, 'UTF-8'); ?></h2>

        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />

        <label for="username" class="block mb-2"><?php echo htmlspecialchars($lang['login_username'], ENT_QUOTES, 'UTF-8'); ?></label>
        <input type="text" name="username" id="username" required class="w-full p-2 mb-4 border border-gray-300 rounded" />

        <label for="password" class="block mb-2"><?php echo htmlspecialchars($lang['login_password'], ENT_QUOTES, 'UTF-8'); ?></label>
        <input type="password" name="password" id="password" required class="w-full p-2 mb-6 border border-gray-300 rounded" />

        <button type="submit" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-blue-800 w-full">
            <?php echo htmlspecialchars($lang['login_button'], ENT_QUOTES, 'UTF-8'); ?>
        </button>

        <?php if (isset($_GET['error'])): ?>
            <p class="text-red-600 mt-4 text-center"><?php echo htmlspecialchars($lang['login_error'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if (isset($_GET['timeout'])): ?>
            <p class="text-yellow-600 mt-4 text-center">Sesión expirada, por favor ingrese nuevamente.</p>
        <?php endif; ?>
    </form>
</body>
</html>