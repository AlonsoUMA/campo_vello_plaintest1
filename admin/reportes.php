<?php
// Incluye el archivo de autocarga (autoload) de Composer
require '../vendor/autoload.php'; 

// Usa la clase de Dompdf
use Dompdf\Dompdf; 
use Dompdf\Options; 

/**
 * 1. Lógica y Seguridad
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';

// Redirigir si el usuario no es administrador
if (!is_admin()) {
    header('Location: ../index.php');
    exit;
}

$pdo = getPDO();

// Obtener estadísticas clave
$totalProducts = $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
$totalUsers = $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

// ✔ Ventas de hoy (versión MySQL)
$totalSalesToday = $pdo->query("
    SELECT SUM(total) 
    FROM facturas 
    WHERE DATE(created_at) = CURDATE()
")->fetchColumn() ?: 0;

// ✔ Ventas del mes actual (versión MySQL)
$totalSalesMonth = $pdo->query("
    SELECT SUM(total) 
    FROM facturas 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
")->fetchColumn() ?: 0;

?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav>
    <div style="display:flex;gap:12px;align-items:center">
        <img src="../assets/img/logo.svg" style="height:36px">
        <strong>Campo Vello - Admin</strong>
    </div>
    <div>
        <a href="dashboard.php" style="color:#fff" class="btn">Volver al panel</a>
    </div>
</nav>

<div class="container">
    <h2>Dashboard</h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px">
        
        <div class="card">
            <h3>Total Productos</h3>
            <p style="font-size:2em;font-weight:bold;margin:0"><?= $totalProducts ?></p>
        </div>
        
        <div class="card">
            <h3>Total Usuarios</h3>
            <p style="font-size:2em;font-weight:bold;margin:0"><?= $totalUsers ?></p>
        </div>
        
        <div class="card">
            <h3>Ventas Hoy</h3>
            <p style="font-size:2em;font-weight:bold;margin:0">$<?= number_format($totalSalesToday, 2) ?></p>
        </div>
        
        <div class="card">
            <h3>Ventas Mes</h3>
            <p style="font-size:2em;font-weight:bold;margin:0">$<?= number_format($totalSalesMonth, 2) ?></p>
        </div>

    </div>

    <h3>Gestión</h3>
    <div style="display:flex;gap:15px;flex-wrap:wrap">
        <a href="gestionar_productos.php" class="btn">Gestionar Productos</a>
        <a href="gestionar_usuarios.php" class="btn">Gestionar Usuarios</a>
        <a href="reportes.php" class="btn">Ver Reportes</a>
    </div>
</div>

</body>
</html>
