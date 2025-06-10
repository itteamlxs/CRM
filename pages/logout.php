<?php
/**
 * Archivo: logout.php
 * Función: Cierra sesión y redirige al login.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_unset();
session_destroy();

header('Location: ' . url('pages/login.php'));
exit;