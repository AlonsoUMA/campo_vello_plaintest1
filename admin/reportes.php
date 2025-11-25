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

// Ventas de hoy (versión MySQL)
$totalSalesToday = $pdo->query("
    SELECT SUM(total) 
    FROM facturas 
    WHERE DATE(created_at) = CURDATE()
")->fetchColumn() ?: 0;

// Ventas del mes actual (versión MySQL)
$totalSalesMonth = $pdo->query("
    SELECT SUM(total) 
    FROM facturas 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
")->fetchColumn() ?: 0;

// =========================================================================
// --- DATOS PARA GRÁFICOS ---
// =========================================================================

// --- GRÁFICO 1: PRODUCTOS MÁS VENDIDOS (Últimos 12 meses) ---
$topProductsStmt = $pdo->prepare('
    SELECT 
        p.name AS product_name, 
        SUM(ii.quantity) AS total_sold
    FROM invoice_items ii
    JOIN facturas f ON ii.invoice_id = f.id
    JOIN productos p ON ii.product_id = p.id
    WHERE f.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY p.name
    ORDER BY total_sold DESC
    LIMIT 10
');
$topProductsStmt->execute();
$topProducts = $topProductsStmt->fetchAll();

// Formato para Google Charts: [['Producto', 'Unidades'], ['Nombre', 10], ...]
$chart1Data = [['Producto', 'Unidades Vendidas']];
foreach ($topProducts as $p) {
    $chart1Data[] = [$p['product_name'], (int)$p['total_sold']];
}
$chart1DataJson = json_encode($chart1Data);


// --- GRÁFICO 2: INGRESOS MENSUALES (Últimos 12 meses) ---
// Establecer el idioma para los nombres de meses (útil para MySQL)
$pdo->exec("SET lc_time_names = 'es_ES'"); 
$monthlyRevenueStmt = $pdo->prepare('
    SELECT 
        DATE_FORMAT(created_at, "%Y-%m") AS sales_month_key,
        DATE_FORMAT(created_at, "%b %Y") AS sales_month_label,
        SUM(total) AS monthly_revenue
    FROM facturas
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY sales_month_key, sales_month_label
    ORDER BY sales_month_key ASC
');
$monthlyRevenueStmt->execute();
$monthlyRevenue = $monthlyRevenueStmt->fetchAll();

// Formato para Google Charts: [['Mes', 'Ingresos'], ['Ene 2024', 1234.56], ...]
$chart2Data = [['Mes', 'Ingresos (USD)']];
foreach ($monthlyRevenue as $m) {
    // Aseguramos que los ingresos sean un número flotante
    $chart2Data[] = [$m['sales_month_label'], (float)$m['monthly_revenue']]; 
}
$chart2DataJson = json_encode($chart2Data);

?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        // 2. Define las funciones para dibujar los gráficos
        google.charts.load('current', {'packages':['corechart', 'bar']});
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            // ===============================================
            // GRÁFICO 1: PRODUCTOS MÁS VENDIDOS (Gráfico de Barras)
            // ===============================================
            var data1 = google.visualization.arrayToDataTable(<?= $chart1DataJson ?>);
            
            var options1 = {
                title: 'Top 10 Productos Más Vendidos (Últimos 12 Meses)',
                chartArea: {width: '60%'},
                hAxis: {
                    title: 'Unidades Vendidas',
                    minValue: 0
                },
                vAxis: {
                    title: 'Producto'
                },
                legend: { position: "none" }
            };

            var chart1 = new google.visualization.BarChart(document.getElementById('chart_top_products'));
            chart1.draw(data1, options1);

            // ===============================================
            // GRÁFICO 2: INGRESOS TOTALES POR MES (Gráfico de Línea)
            // ===============================================
            var data2 = google.visualization.arrayToDataTable(<?= $chart2DataJson ?>);

            var options2 = {
                title: 'Ingresos Totales por Mes (Últimos 12 Meses)',
                curveType: 'function',
                legend: { position: 'bottom' },
                vAxis: {
                    title: 'Ingresos (USD)',
                    format: '$#,###.00' 
                }
            };

            var chart2 = new google.visualization.LineChart(document.getElementById('chart_monthly_revenue'));
            chart2.draw(data2, options2);
        }
    </script>
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

    <div class="container-fluid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px">
        
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
    
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(450px,1fr));gap:30px;margin-top:30px">
        
        <div id="chart_top_products" style="height: 400px; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-radius: 8px; background: #fff;">
            </div>

        <div id="chart_monthly_revenue" style="height: 400px; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-radius: 8px; background: #fff;">
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


</body>
</html>