<?php
// Incluye las funciones de autenticación
require_once __DIR__ . '/includes/autenticacion.php';

// Cierra la sesión del usuario
logout_user();

// Redirige al usuario a la página de inicio
header('Location: index.php'); 
exit;
?>