<?php
// ===================================
// 1. DEPENDENCIAS y LÓGICA DE CONTROL
// ===================================
// Carga la configuración base (DB, CSRF, Session)
require_once __DIR__ . '/includes/config.php';
// Carga las funciones de autenticación (is_logged, is_admin, login_user)
require_once __DIR__ . '/includes/autenticacion.php';
// Carga las funciones de utilidad (csrf_field, verify_csrf)
require_once __DIR__ . '/includes/funciones.php'; 

$error = null;

// --- LÓGICA DE INICIO DE SESIÓN (MODIFICADA) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['_csrf'] ?? '';

    // 1. Verificar CSRF
    if (!verify_csrf($csrf_token)) {
        $error = "Error de seguridad. Intente de nuevo.";
    } 
    // 2. Intentar autenticar al usuario
    elseif (login_user($email, $password)) {
        // Redirección exitosa (la lógica de redirección se ejecuta después)
        // No necesitamos hacer nada aquí, ya que la lógica general de redirección
        // se maneja al final de esta sección de control.
    } 
    // 3. Autenticación fallida
    else {
        $error = "Credenciales incorrectas. Verifique su email y contraseña.";
    }
}
// --- FIN LÓGICA DE INICIO DE SESIÓN ---


// Redirección si el usuario ya está logueado O si el login POST fue exitoso
if (is_logged()) {
    if (is_admin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: cajero/ventas.php');
    }
    exit;
}

// Si hubo un error en el intento de POST, lo mostramos.
// Si no hubo POST, la variable $error es null.
// Esta sección reemplaza el manejo de errores de $_SESSION['error'] si se usaba un login.php
// $error = $_SESSION['error'] ?? null;
// unset($_SESSION['error']);
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
        
        <form method="post" action="index.php" style="max-width:420px;margin:0 auto;display:flex;flex-direction:column;gap:8px;">
            
            <?php 
            echo csrf_field(); 
            ?>
            
            <label for="email">Email</label>
            <input type="email" name="email" id="email" pattern="^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,15}$"
                title="Debe tener formato de correo electrónico (ej: nombre@dominio.com)" required
            >
            
            <label for="password">Contraseña</label>
            <input type="password" name="password" id="password"
                
                title="Debe tener entre 8 y 15 caracteres, incluyendo al menos una letra mayuscula, una minuscula, sin tildes y un número."
                required
            >
            
            <button class="btn" style="margin-top:12px;">Entrar</button>
        </form>
    </div>

    </div>
    
    
     <footer style="
        position: fixed; 
        bottom: 0; 
        width: 100%; 
        text-align: center; 
        padding: 10px 0; 
        background: #f4f4f9; /* Fondo similar al body */
        border-top: 1px solid #ddd;
        font-size: 0.85em;
        color: #555;
    ">
        &copy; <?= date('Y') ?> Campo Vello. Todos los derechos reservados.
    </footer>


</body>
</html>



    <!--pattern="(?=.*[A-Za-zñÑ])(?=.*\d)[A-Za-zñÑ\d]{8,15}"-->