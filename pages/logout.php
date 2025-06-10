<?php
/**
 * Archivo: logout.php
 * Función: Cierra sesión y redirige al login.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_unset();
session_destroy();

header('Location: /pages/login.php');
exit;
