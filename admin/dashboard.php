<?php
/**
 * ================================
 * Dashboard de Administrador
 * Campo Vello - Sistema de Facturación
 * ================================
 * Este archivo muestra:
 * - Estadísticas generales
 * - Accesos rápidos para el administrador
 * - Alertas de stock bajo
 */

// ================================
// Importar configuraciones y funciones
// ================================
require_once __DIR__ . '/../includes/config.php';            // Configuración general y conexión
require_once __DIR__ . '/../includes/autenticacion.php';     // Verificación de roles y sesiones
require_once __DIR__ . '/../includes/funciones.php';         // Funciones generales del sistema

// ================================
// Validar que el usuario sea administrador
// ================================
if (!is_admin()) {
    header('Location: ../index.php');   // Si no es admin → redirigir
    exit;
}

// ================================
// Conexión PDO
// ================================
$pdo = getPDO();

// ================================
// Consultas de métricas del sistema
// ================================

// Total de productos registrados
$totalProducts = $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();

// Total de clientes registrados
$totalClients = $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();

// Total de facturas generadas
$totalInvoices = $pdo->query('SELECT COUNT(*) FROM facturas')->fetchColumn();

// Productos con stock bajo (<5)
$lowStock = $pdo->query('SELECT COUNT(*) FROM productos WHERE stock < 5')->fetchColumn();
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - Dashboard</title>

    <!-- Hoja de estilos -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <!-- ============================
         Barra de navegación superior
    ============================= -->
    <nav>
        <div style="display:flex; gap:12px; align-items:center">
            <img src="../assets/img/logo.svg" style="height:36px">
            <strong>Campo Vello - Admin</strong>
        </div>

        <div>
            <a href="../logout.php" class="btn" style="background-color:#d9534f;">Cerrar Sesión</a>
        </div>
    </nav>

    <!-- ============================
         Contenido principal
    ============================= -->
    <div class="container">

        <h2>Panel de administrador</h2>

        <!-- Estadísticas generales -->
        <p>
            Productos: <?= $totalProducts ?> |
            Clientes: <?= $totalClients ?> |
            Facturas: <?= $totalInvoices ?>
        </p>

        <!-- Alerta de stock bajo -->
        <?php if ($lowStock > 0): ?>
            <div class="alert">
                Hay <?= $lowStock ?> producto(s) con stock menor a 5.
            </div>
        <?php endif; ?>

        <!-- Accesos rápidos -->
        <div style="display:flex; gap:12px; margin-bottom:14px; flex-wrap:wrap;">
            <a class="btn" href="gestionar_productos.php">Gestionar productos</a>
            <a class="btn" href="gestionar_usuarios.php">Gestionar usuarios</a>
            <a class="btn" href="reportes.php">Reportes</a>
        </div>

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

    <script src="assets/js/login_animated.js"></script> 

</body>
</html>

