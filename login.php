<?php
// Incluye configuración y funciones de autenticación
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/autenticacion.php';

// --- 1. Verificación del Método de Solicitud ---
// Si no es una solicitud POST, redirige a la página principal.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    header('Location: index.php'); 
    exit; 
}

// --- 2. Verificación de Seguridad CSRF ---
// Verifica el token CSRF para prevenir ataques de falsificación de solicitudes.
if (!verify_csrf($_POST['_csrf'] ?? '')) { 
    $_SESSION['error'] = 'Token CSRF inválido'; 
    header('Location: index.php'); 
    exit; 
}

// --- 3. Intento de Autenticación ---
$email = trim($_POST['email'] ?? ''); 
$pass = $_POST['password'] ?? '';

if (login_user($email, $pass)) {
    // Autenticación exitosa: Redirige según el rol del usuario
    if (is_admin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: cajero/ventas.php');
    }
    exit;
} else {
    // Autenticación fallida: Establece un error y redirige a la página principal
    $_SESSION['error'] = 'Credenciales inválidas'; 
    header('Location: index.php'); 
    exit;
}
?>