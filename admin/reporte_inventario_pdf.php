<?php
// Incluye el archivo de autocarga (autoload) de Composer
require '../vendor/autoload.php';

// === SOLUCIÓN AL PROBLEMA DE LA HORA ADELANTADA ===
// Establece la zona horaria a El Salvador (o la que corresponda a tu ubicación)
date_default_timezone_set('America/El_Salvador');
// ==================================================

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

// Obtener datos para el reporte (Inventario Completo)
// ------------------------------------------------------------------
// CORRECCIÓN: Se eliminó el 'JOIN' duplicado.
// ------------------------------------------------------------------
$products = $pdo->query('
    SELECT p.*, c.name as category 
    FROM productos p 
    LEFT JOIN categorias c ON p.category_id = c.id 
    ORDER BY p.id ASC
')->fetchAll();


// -------------------------------------------------------------------------
// --- CÁLCULO DEL VALOR TOTAL DEL INVENTARIO ---
// -------------------------------------------------------------------------
$total_inventory_value = 0;
foreach ($products as $p) {
    // Acumular el valor total (Precio * Stock)
    $total_inventory_value += ((float) $p['price'] * (int) $p['stock']);
}

// Formatear el total para mostrarlo con dos decimales
$total_inventory_formatted = '$' . number_format($total_inventory_value, 2);
// -------------------------------------------------------------------------


// =========================================================================
// --- GENERACIÓN DEL CONTENIDO HTML DEL REPORTE ---
// =========================================================================

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario Físico</title>
    <style>
        /* Estilos Generales y del Encabezado (Inspirados en la factura de Campo Vello) */
        body { font-family: sans-serif; margin: 0; padding: 0; }
        
        /* Definición de la paleta de verdes de Campo Vello */
        :root {
            --green-dark: #388E3C; 
            --green-medium: #4CAF50; 
            --green-light: #66BB6A; 
            --green-bg-table: #E8F5E9; 
        }
        
        /* Contenedor del encabezado: Fondo verde y efecto de onda */
        .header-bg {
            background-color: var(--green-medium); 
            padding: 10px 0;
            position: relative;
            height: 60px; 
            overflow: hidden; 
        }

        /* Simula la forma de onda/triángulo de la factura */
        .header-wave {
            background-color: var(--green-light); 
            position: absolute;
            top: 0;
            left: -100px;
            width: 200px; 
            height: 100px;
            transform: skewX(-30deg); 
            z-index: 1;
        }

        .logo-container {
            width: 100%;
            text-align: center;
            position: relative; 
            z-index: 2; 
        }
        
        /* Texto principal del encabezado */
        .header-text {
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 2px;
            line-height: 1;
        }
        /* Subtítulo */
        .header-subtext {
            color: white;
            font-size: 12px;
            line-height: 1;
        }
        
        h1 { 
            text-align: center; 
            color: var(--green-dark); 
            margin-top: 30px;
            margin-bottom: 5px;
            font-size: 18px;
            text-transform: uppercase;
        }
        /* MOSTRANDO LA FECHA Y HORA DE GENERACIÓN */
        .reporte-info { 
            text-align: right; 
            font-size: 10px; 
            margin: 0 30px 10px 0;
            color: #555;
        }
        .content {
            padding: 0 30px;
        }

        /* Estilos de la Tabla */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px;
            border: 1px solid #ddd;
        }
        th, td { 
            border: 1px solid #e0e0e0;
            padding: 6px 4px; 
            text-align: left; 
            font-size: 9px; 
        }
        th { 
            background-color: var(--green-bg-table); 
            color: var(--green-dark); 
            font-weight: bold;
            text-align: center;
            border-color: #C8E6C9; 
        }
        /* Rayado de la tabla en un verde muy sutil */
        tr:nth-child(even) {
            background-color: #f5fff5; 
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        /* Celdas de revisión en blanco */
        .col-revision { 
            background-color: white !important; 
            width: 10%; 
        }
        
        /* Clase para centrar el stock */
        .col-stock {
            text-align: center; 
            width: 8%; 
        }
        
    </style>
</head>
<body>
    
    <div class="header-bg">
        <div class="header-wave"></div>
        
        <div class="logo-container">
            <div class="header-text">CAMPO VELLO</div>
            <div class="header-subtext">Sistemas de Facturación e Inventario</div>
        </div>
    </div>
    
    <div class="content">

        <h1>REPORTE DE INVENTARIO FÍSICO</h1>
        <p class="reporte-info">Generado el: ' . date('d/m/Y H:i') . '</p>
    
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">ID</th>
                    <th style="width: 30%;">Nombre</th>
                    <th style="width: 15%;">Categoría</th>
                    <th style="width: 10%;">Ubicación</th>
                    <th style="width: 10%;">Precio</th>
                    
                    <th class="col-stock">Stock (Sistema)</th>
                    
                    <th class="col-revision">Existencia Física</th>
                    <th class="col-revision">Diferencia</th>
                </tr>
            </thead>
            <tbody>';

// Recorrer los productos y generar las filas de la tabla
foreach ($products as $p) {
    $html .= '
                <tr>
                    <td class="text-center">' . htmlspecialchars($p['id']) . '</td>
                    <td>' . htmlspecialchars($p['name']) . '</td>
                    <td>' . htmlspecialchars($p['category'] ?? 'N/A') . '</td>
                    <td class="text-center">' . htmlspecialchars($p['location']) . '</td>
                    <td class="text-right">$' . number_format($p['price'], 2) . '</td>
                    
                    <td class="col-stock">' . htmlspecialchars($p['stock']) . '</td>
                    
                    <td class="col-revision"></td>
                    <td class="col-revision"></td>
                </tr>';
}

$html .= '
            </tbody>
        </table>
        
        <div style="width: 300px; margin-top: 15px; margin-left: auto; border: 1px solid var(--green-dark);">
            <div style="display: flex; background-color: var(--green-bg-table); padding: 5px; border-bottom: 1px solid var(--green-dark);">
                <div style="font-size: 11px; font-weight: bold; color: var(--green-dark); flex-grow: 1; text-align: left; padding-left: 5px;">
                    VALOR TOTAL DE INVENTARIO (Sistema)
                </div>
                <div style="font-size: 11px; font-weight: bold; color: var(--green-dark); text-align: right; padding-right: 5px;">
                    ' . $total_inventory_formatted . '
                </div>
            </div>
        </div>
        <p style="text-align: center; font-size: 10px; margin-top: 30px; color: #555;">
            Gracias por su confianza. Campo Vello: su aliado en el campo.
        </p>
    </div>

    
</body>
</html>';

// =========================================================================
// --- CONFIGURACIÓN Y GENERACIÓN DEL PDF CON DOMPDF ---
// =========================================================================

// Configurar opciones de Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);


$dompdf = new Dompdf($options);

// Cargar el HTML en Dompdf
$dompdf->loadHtml($html);

// Formato de hoja Vertical
$dompdf->setPaper('A4', 'portrait');

// Renderizar el HTML a PDF
$dompdf->render();

// Enviar el PDF al navegador para que se muestre en línea (Attachment => false)
$dompdf->stream("Reporte_Inventario_" . date('Ymd') . ".pdf", array("Attachment" => false));



exit;
