<?php
// ... (código PHP de control de acceso y dependencias sin cambios)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';

if (!is_logged() || is_admin()) {
    header('Location: ../index.php');
    exit;
}

$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cajero - Nuevo Cliente</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    
    <nav>
        <div style="display:flex;align-items:center;gap:12px">
            <img src="../assets/img/logo.svg" style="height:48px" alt="Logo">
            <strong>Panel de Cajero</strong>
        </div>
        <div>
            <a href="ventas.php" class="btn">Volver a Ventas</a>
            <a href="../logout.php" class="btn" style="background-color:#d9534f;">Cerrar Sesión</a>
        </div>
    </nav>
    
    <div class="container">
        <h2>Registrar Nuevo Cliente</h2>
        
        <?php 
        if($mensaje) {
            $clase = (strpos($mensaje, 'exitosamente') !== false) ? 'alert success' : 'alert';
            echo '<div class="'.$clase.'">'.htmlspecialchars($mensaje).'</div>'; 
        } 
        ?>
        
        <form method="post" action="guardar_cliente.php" style="max-width:500px;margin:0 auto;display:flex;flex-direction:column;gap:12px;">
            
            <?php 
            echo csrf_field(); 
            ?>
            
            <label for="name">Nombre Completo</label>
            <input type="text" name="name" id="name" required maxlength="50"> 
            
            <label for="phone">Teléfono (9 dígitos con guion)</label>
            <input type="text" name="phone" id="phone" maxlength="9"  placeholder="Ej: 0000-0000">
            
            <label for="email">Email</label>
            <input type="email" name="email" id="email" maxlength="50"placeholder="Ej: Julano@123.com">
            
            
            <label for="nit">Dui (Número de Identificación Personal)</label>
            <input type="text" name="nit" id="nit" maxlength="20" placeholder="Ej: 00000000-0"> 
            
            <label for="address">Dirección</label>
            <textarea name="address" id="address" rows="3" maxlength="255"></textarea> 

            <div style="text-align:center;margin-top:16px;">
                <button type="submit" class="btn" style="background-color:#5cb85c;">Guardar Cliente</button>
            </div>
        </form>
    </div>
</body>
</html>