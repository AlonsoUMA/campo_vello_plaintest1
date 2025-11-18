<?php

// ----------------------------------------------------------------------
// CONFIGURACIÓN, INCLUSIÓN DE LIBRERÍAS Y USO DE NAMESPACE
// ----------------------------------------------------------------------

// NOTA: Estas rutas asumen que 'factura_pdf.php' está en un subdirectorio (ej: /pruebas/)
// y que /includes/ y /vendor/ están en el directorio raíz. Debes verificar tus rutas.
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Asegurar que el usuario esté logeado
require_login();

// ----------------------------------------------------------------------
// OBTENCIÓN DE DATOS DE LA FACTURA
// ----------------------------------------------------------------------

// Conexión a la base de datos y obtención del ID
$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: ventas.php');
    exit;
}

// 1. Obtener datos de la factura, cliente y usuario
$stmt = $pdo->prepare('SELECT f.*, 
                            u.nombre as user_name, 
                            cl.name as client_name, 
                            cl.nit as client_nit,
                            cl.phone as client_phone,
                            cl.email as client_email,
                            cl.address as client_address
                     FROM facturas f 
                     LEFT JOIN usuarios u ON f.user_id=u.id 
                     LEFT JOIN clientes cl ON f.client_id=cl.id 
                     WHERE f.id = ?');

$stmt->execute([$id]); 
$invoice = $stmt->fetch();

if (!$invoice) {
    die('Factura no encontrada'); 
}

// 2. Obtener items de la factura
$itemsStmt = $pdo->prepare('SELECT ii.*, p.name 
                            FROM invoice_items ii 
                            JOIN productos p ON ii.product_id=p.id 
                            WHERE ii.invoice_id=?');

$itemsStmt->execute([$id]); 
$items = $itemsStmt->fetchAll();

// 3. Preparación del logo (Codificación Base64 para Dompdf)
// Dompdf requiere a menudo que las imágenes locales se incrusten usando Base64.
$logoPath = realpath(__DIR__ . '/../assets/img/logo.svg'); 

$logoDataUri = '';
if ($logoPath && file_exists($logoPath)) {
    $logoContent = file_get_contents($logoPath);
    // MIME type para SVG
    $logoDataUri = 'data:image/svg+xml;base64,' . base64_encode($logoContent);
}

// Determinar el porcentaje de IVA
$subtotal_val = $invoice['subtotal'] ?? 0;
$iva_amount_val = $invoice['iva_amount'] ?? 0;
// Cálculo del porcentaje de IVA para mostrar en la tabla de totales
$iva_rate_percent = ($subtotal_val > 0) ? round(($iva_amount_val / $subtotal_val) * 100) : 0;

// ----------------------------------------------------------------------
// GENERACIÓN DEL CONTENIDO HTML Y CSS
// ----------------------------------------------------------------------
ob_start();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* Definición de colores temáticos */
        :root {
            --green-dark: #2e7d32;
            --green-light: #e8f5e9;
            --font-color: #1b3b18;
            --border-color: #a5d6a7;
        }

        /* Estilos generales */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: var(--font-color);
            font-size: 10pt;
        }

        .container {
            padding: 20px;
        }

        /* 1. CABECERA: Solo Logo */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px; 
            padding: 0; 
            border: none;
        }

        /* Estilos del logo - AHORA AL 100% DEL ANCHO DE LA PÁGINA */
        .company-info {
            width: 100%; /* Ocupa todo el ancho disponible en la cabecera */
            float: left;
            text-align: center; /* Centramos el logo horizontalmente */
        }
        .logo-container {
            width: 100%; /* El contenedor del logo ahora ocupa el 100% del ancho de .company-info */
            height: 100px; /* Aumentamos la altura del contenedor para que el logo tenga espacio vertical */
            display: block; 
            margin: 0 auto; /* Centrar el contenedor */
        }
        .logo-img {
            max-width: 100%; 
            max-height: 100%; 
            width: 100%; /* La imagen ocupa el 100% del ancho de .logo-container */
            height: auto; /* La altura se ajusta automáticamente para mantener la proporción */
            object-fit: contain; /* Asegura que el SVG mantenga su relación de aspecto */
        }
        
        /* Ocultamos el bloque de la derecha que ya no tiene contenido */
        .invoice-info {
            width: 0; 
            padding: 0;
            margin: 0;
            border: none;
            background-color: transparent;
        }

        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }

        /* 2. DETALLES DE CONTACTO Y CLIENTE */
        .contact-details-row {
            margin-top: 15px;
            margin-bottom: 20px;
            display: flex;
            width: 100%;
        }

        /* Bloque de detalles de la empresa (IZQUIERDA) */
        .company-details-box {
            width: 40%; 
            float: left;
            border-left: 3px solid var(--green-dark); 
            padding: 10px;
            font-size: 10pt;
            background-color: #f7fff7;
        }

        /* Bloque de detalles del cliente (DERECHA - Desplazado) */
        .client-details-box {
            margin-left: 10%; 
            width: 50%; 
            float: right;
            border-right: 3px solid var(--green-dark); 
            padding: 10px;
            font-size: 10pt;
            background-color: #f7fff7;
        }

        .company-details-box div,
        .client-details-box div {
            margin: 5px 0;
            text-align: left;
        }

        /* Tabla de ítems */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1); 
        }
        .items-table th,
        .items-table td {
            border: 1px solid var(--border-color); 
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: var(--green-dark);
            color: #fff;
            font-size: 10pt;
            text-align: center;
        }
        .items-table td:nth-child(3), 
        .items-table td:nth-child(4), 
        .items-table td:nth-child(5) { 
            text-align: right;
        }

        /* Sección de totales */
        .totals-section {
            width: 40%;
            float: right;
            border: 1px solid var(--green-dark);
            background-color: var(--green-light);
            padding: 10px;
            border-radius: 5px;
        }
        .totals-section table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        .totals-section table td {
            padding: 5px 0;
            border: none;
        }
        .totals-section .total-label {
            text-align: right;
            font-weight: bold;
            padding-right: 10px;
            color: var(--font-color);
        }
        .totals-section .total-amount {
            text-align: right;
            font-weight: bold;
            color: var(--font-color);
        }
        .totals-section .final-total .total-label,
        .totals-section .final-total .total-amount {
            font-size: 14pt; 
            color: var(--green-dark);
            border-top: 2px solid var(--green-dark);
            padding-top: 8px;
            font-weight: bold;
        }
        
        .footer-text {
            margin-top: 30px;
            font-size: 9pt;
            text-align: center;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="header clearfix">
            
            <div class="company-info">
                <div class="logo-container">
                    <?php if (!empty($logoDataUri)): ?>
                        <img src="<?php echo $logoDataUri; ?>" class="logo-img" alt="Logo">
                    <?php else: ?>
                        <h1 style="color:var(--green-dark); margin:0;">CAMPO VELLO</h1>
                    <?php endif; ?>
                </div>
            </div>

            <div class="invoice-info">
            </div>
        </div> 
        
        <div class="clearfix"></div> 
        
        <div class="contact-details-row clearfix">
            
            <div class="company-details-box">
                <h1 style="font-size: 14pt; margin: 0 0 10px 0; color: var(--green-dark);">
                    FACTURA DE VENTA
                </h1>
                <div style="font-size: 12pt; margin-bottom: 5px; color: var(--green-dark);">
                    <strong>CAMPO VELLO</strong>
                </div>
                
                <p style="margin: 2px 0;"><strong>No. de Factura:</strong> <?php echo htmlspecialchars($invoice['id']); ?></p>
                <p style="margin: 2px 0;"><strong>Fecha de Venta:</strong> <?php echo date('d/m/Y', strtotime($invoice['created_at'] ?? 'now')); ?></p>
                
                <p style="margin: 2px 0;"><strong>Dirección:</strong> Av Principal #123, El Calvario, Sonsonate</p>
                <p style="margin: 2px 0;"><strong>Teléfono:</strong> (503) 4456-7890</p>
                <p style="margin: 2px 0;"><strong>Cajero:</strong> <?php echo htmlspecialchars($invoice['user_name'] ?? 'N/A'); ?></p>
            </div>
            
            <div class="client-details-box">
                <div style="font-size: 12pt; margin-bottom: 5px; color: var(--green-dark); text-align: left;">
                    <strong>DETALLES DEL CLIENTE</strong>
                </div>
                
                <p><strong>CLIENTE:</strong> <?php echo htmlspecialchars($invoice['client_name'] ?? 'Consumidor Final'); ?></p>
                
                <?php if(!empty($invoice['client_phone'])): ?>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($invoice['client_phone']); ?></p>
                <?php endif; ?>
                <?php if(!empty($invoice['client_email'])): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($invoice['client_email']); ?></p>
                <?php endif; ?>
                <?php if(!empty($invoice['client_nit'])): ?>
                    <p><strong>DUi:</strong> <?php echo htmlspecialchars($invoice['client_nit']); ?></p>
                <?php endif; ?>

                <?php if(!empty($invoice['client_address'])): ?>
                    <p><strong>Dirección:</strong> <?php echo htmlspecialchars($invoice['client_address']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="clearfix"></div> 

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">ITEM</th>
                    <th style="width: 50%;">DESCRIPCIÓN</th>
                    <th style="width: 15%;">VALOR UNITARIO</th>
                    <th style="width: 10%;">CANT</th>
                    <th style="width: 20%;">VALOR TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php $item_number = 1; ?>
                <?php foreach($items as $it): ?>
                <tr>
                    <td style="text-align: center;"><?php echo $item_number++; ?></td>
                    <td><?php echo htmlspecialchars($it['name']); ?></td>
                    <td>$<?php echo number_format($it['price'], 2); ?></td>
                    <td><?php echo htmlspecialchars($it['quantity']); ?></td>
                    <td>$<?php echo number_format($it['price'] * $it['quantity'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-section clearfix">
            <table>
                <tr>
                    <td class="total-label">SUBTOTAL:</td>
                    <td class="total-amount">$<?php echo number_format($invoice['subtotal'] ?? '0.00', 2); ?></td>
                </tr>
                <tr>
                    <td class="total-label">IVA (<?php echo $iva_rate_percent; ?>%):</td>
                    <td class="total-amount">$<?php echo number_format($invoice['iva_amount'] ?? '0.00', 2); ?></td>
                </tr>
                <tr class="final-total">
                    <td class="total-label">TOTAL A PAGAR:</td>
                    <td class="total-amount">$<?php echo number_format($invoice['total'] ?? '0.00', 2); ?></td>
                </tr>
            </table>
        </div>
        
        <div class="clearfix"></div> 

        <div class="footer-text">
            <h2>Gracias por su compra, Campo Vello, su aliado en el campo.</h2>
        </div>

    </div> </body>
</html>
<?php
// ----------------------------------------------------------------------
// GENERACIÓN Y SALIDA DEL PDF (DOMPDF)
// ----------------------------------------------------------------------
$html = ob_get_clean();
// Ruta donde se guardará el archivo en el servidor
$path = __DIR__ . '/../facturas/factura_' . $invoice['id'] . '.pdf';

// Uso de Dompdf (Asegúrate de que la clase esté disponible)
if (class_exists('Dompdf\Dompdf')) { 
    $options = new Options(); 
    // Habilitar la carga remota es crucial para algunas imágenes/estilos en Dompdf
    $options->set('isRemoteEnabled', true); 
    $dompdf = new Dompdf($options); 
} else {
    // Respaldo por si hay un error de namespace
    $dompdf = class_exists('Dompdf') ? new Dompdf() : null; 
}

if (isset($dompdf)) {
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();
    $output = $dompdf->output();
    
    // Guardar el PDF en el servidor
    file_put_contents($path, $output);
    
    // Enviar el PDF directamente al navegador
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="factura_'.$invoice['id'].'.pdf"');
    echo $output; 
    exit;
}

// Respaldo en caso de fallo crítico de Dompdf (redirige)
file_put_contents($path, $html);
header('Location: ventas.php');
exit;
?>