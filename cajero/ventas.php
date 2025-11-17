<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';

require_login();
$pdo = getPDO();
// MODIFICACIÓN DE VARIABLE/COLUMNA: 'cl.nombre' cambiado a 'cl.name' para coincidir con la DB
$recent = $pdo->query('SELECT f.*, u.nombre as vendedor, cl.name as cliente FROM facturas f LEFT JOIN usuarios u ON f.user_id=u.id LEFT JOIN clientes cl ON f.client_id=cl.id ORDER BY f.id DESC LIMIT 10')->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cajero - Ventas</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav>
        <div style="display:flex;gap:12px;align-items:center">
            <img src="../assets/img/logo.svg" style="height:36px">
            <strong>Campo Vello - Cajero</strong>
        </div>
        <div>
            <a href="../logout.php" class="btn" style="background-color:#d9534f;">Cerrar Sesión</a>
        </div>
    </nav>
    <div class="container">
        <h2>Panel Cajero</h2>
        <div style="display:flex;gap:12px;margin-bottom:12px;">
            <a class="btn" href="nueva_factura.php">Nueva factura</a>
            <a href="nuevo_cliente.php" class="btn" style="background-color:verde;">  Registrar Cliente </a>
        </div>

        <h3>Facturas recientes</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recent as $r): ?>
                    <tr>
                        <td><?=$r['id']?></td>
                        <td><?=$r['created_at']?></td>
                        <td><?=htmlspecialchars($r['cliente'])?></td>
                        <td>$<?=number_format($r['total'],2)?></td>
                        <td><a class="btn" href="generar_pdf.php?id=<?=$r['id']?>" target="_blank">Ver PDF</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>