<?php
// ===================================
// 1. DEPENDENCIAS y LÓGICA DE CONTROL
// ===================================
// Carga la configuración base (DB, CSRF, Session)
require_once __DIR__ . '/includes/config.php';
// Carga las funciones de autenticación (is_logged, is_admin)
require_once __DIR__ . '/includes/autenticacion.php';

// Redirección si el usuario ya está logueado
if (is_logged()) {
    if (is_admin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: cajero/ventas.php');
    }
    exit;
}

// Manejo de errores de inicio de sesión
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Campo Vello - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    
    <nav>
        <div style="display:flex;align-items:center;gap:12px">
            <img src="assets/img/logo.svg" style="height:48px" alt="Logo Campo Vello">
            <strong>Campo Vello</strong>
        </div>
    </nav>
    
    <div class="container">
        <h2>Iniciar sesión</h2>
        
        <?php
        // Mostrar mensaje de error si existe
        if($error) {
            echo '<div class="alert">'.htmlspecialchars($error).'</div>'; 
        } 
        ?>
        
        <form method="post" action="login.php" style="max-width:420px;margin:0 auto;display:flex;flex-direction:column;gap:8px;">
            
            <?php 
            // Campo de seguridad CSRF
            echo csrf_field(); 
            ?>
            
            <label for="email">Email</label>
            <input type="email" class="form-control" name="email" id="email" required>
                
            
            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password" required>
            
            <div style="text-align:center;margin-top:8px;">
                <button class="btn">Ingresar</button>
            </div>
        </form>
        
        <p style="text-align:center;color:#666;margin-top:12px">
        </p>
    </div>
</body>
</html>