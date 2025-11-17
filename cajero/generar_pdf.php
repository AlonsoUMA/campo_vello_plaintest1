<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';

require_login();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: ventas.php');
    exit;
}

$stmt = $pdo->prepare('SELECT f.*, u.nombre as user_name, cl.name as client_name, cl.nit as client_nit 
                      FROM facturas f 
                      LEFT JOIN usuarios u ON f.user_id=u.id 
                      LEFT JOIN clientes cl ON f.client_id=cl.id 
                      WHERE f.id = ?');

$stmt->execute([$id]); 
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Factura no encontrada'); 
}

$itemsStmt = $pdo->prepare('SELECT ii.*, p.name 
                           FROM invoice_items ii 
                           JOIN productos p ON ii.product_id=p.id 
                           WHERE ii.invoice_id=?');

$itemsStmt->execute([$id]); 
$items = $itemsStmt->fetchAll();

ob_start();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial;
            color: #1b3b18;
            margin: 20px
        }
        .header {
            background: #2e7d32;
            color: #fff;
            padding: 12px;
            border-radius: 6px
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px
        }
        .logo img {
            height: 60px
        }
        .content {
            padding: 12px
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px
        }
        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left
        }
        th {
            background: #2e7d32;
            color: #fff
        }
        .total {
            margin-top: 12px;
            font-size: 18px;
            color: #2e7d32;
            font-weight: 700
        }
        @media print {
            .header {
                -webkit-print-color-adjust: exact
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <img src="<?php echo __DIR__.'/../assets/img/logo.svg'; ?>" alt="logo">
            <div>
                <h1 style="margin:0">Campo Vello</h1>
                <div>Sistema de Facturación e Inventario</div>
            </div>
        </div>
    </div>
    <div class="content">
        <h2>Factura #<?php echo $invoice['id']; ?></h2>
        <p>Fecha: <?php echo $invoice['created_at']; ?></p>
        <p>Vendedor: <?php echo htmlspecialchars($invoice['user_name']); ?></p>
        <p>Cliente: <?php echo htmlspecialchars($invoice['client_name'] ?? 'Consumidor Final'); ?> 
            <?php if(!empty($invoice['client_nit'])) echo ' - NIT: '.htmlspecialchars($invoice['client_nit']); ?>
        </p>
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cant</th>
                    <th>Precio</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $it): ?>
                <tr>
                    <td><?php echo htmlspecialchars($it['name']); ?></td>
                    <td><?php echo $it['quantity']; ?></td>
                    <td>$<?php echo number_format($it['price'],2); ?></td>
                    <td>$<?php echo number_format($it['price']*$it['quantity'],2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="total">Total: <?php echo number_format($invoice['total'],2); ?></div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();
$path = __DIR__ . '/../facturas/factura_' . $invoice['id'] . '.pdf';
$vendor = __DIR__ . '/../vendor/autoload.php';

if (file_exists($vendor)) {
    require $vendor;

    if (class_exists('Dompdf\Dompdf') || class_exists('Dompdf')) {
        if (class_exists('Dompdf\\Dompdf')) { 
            $dompdf = new Dompdf\Dompdf(); 
        } else { 
            $dompdf = new Dompdf(); 
        }

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4','portrait');
        $dompdf->render();
        $output = $dompdf->output();
        
        file_put_contents($path, $output);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="factura_'.$invoice['id'].'.pdf"');
        echo $output; 
        exit;
    }
}

// Fallback: guardar HTML con extensión .pdf
file_put_contents($path, $html);

header('Location: ventas.php');
exit;
?>