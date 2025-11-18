<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';

require_login();

$pdo = getPDO();

// --- MODIFICACIÓN DE IVA: Tasa del impuesto ajustada al 13% ---
$iva_rate = 0.13; // 13% de IVA

// Obtener listas para el formulario
$products = $pdo->query('SELECT * FROM productos WHERE stock > 0 ORDER BY name')->fetchAll();

// MODIFICACIÓN: Se usa un ALIAS "name AS nombre" para poder usar la variable $c['nombre'] en el HTML.
$clients = $pdo->query('SELECT id, name AS nombre FROM clientes ORDER BY name')->fetchAll();

$error = null;
$client_id_selected = $_POST['client_id'] ?? ''; // Mantener cliente seleccionado en caso de error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $error = 'CSRF inválido';
    } else {
        $items = $_POST['items'] ?? [];
        $client_id = (int)($_POST['client_id'] ?? 0);
        $client_id_selected = $client_id; // Para mantenerlo seleccionado

        if (empty($items)) {
            $error = 'Debe seleccionar al menos un producto';
        } else {
            try {
                // Iniciar transacción para asegurar la consistencia de los datos (factura e inventario)
                $pdo->beginTransaction();
                $subtotal = 0; // Se usará para el total ANTES de IVA

                // 1. Pre-validación y cálculo del subtotal (con bloqueo de fila FOR UPDATE)
                foreach ($items as $pid => $qty) {
                    $qty = (int)$qty;
                    if ($qty <= 0) continue;

                    // Bloqueo de fila para evitar condiciones de carrera en el stock
                    $p = $pdo->prepare('SELECT * FROM productos WHERE id = ? FOR UPDATE');
                    $p->execute([$pid]);
                    $prod = $p->fetch();

                    if (!$prod) {
                        throw new Exception('Producto no existe: ' . $pid);
                    }
                    if ($prod['stock'] < $qty) {
                        throw new Exception('Stock insuficiente para: ' . htmlspecialchars($prod['name']) . '. Disponible: ' . $prod['stock']);
                    }

                    // Cálculo del subtotal (precio * cantidad)
                    $subtotal += $prod['price'] * $qty;
                }
                
                // --- CÁLCULO DE IVA AL 13% ---
                $iva_amount = $subtotal * $iva_rate;
                $total = $subtotal + $iva_amount;


                // 2. Insertar la factura principal
                $stmt = $pdo->prepare('INSERT INTO facturas (user_id, client_id, subtotal, iva_amount, total) VALUES (?,?,?,?,?)');
                $stmt->execute([$_SESSION['user']['id'], $client_id, $subtotal, $iva_amount, $total]);
                $invoice_id = $pdo->lastInsertId();

                // 3. Insertar los ítems de la factura y actualizar el stock
                foreach ($items as $pid => $qty) {
                    $qty = (int)$qty;
                    if ($qty <= 0) continue;

                    // Re-obtener el precio para asegurar que sea el precio final (aunque ya lo tenemos bloqueado)
                    $p = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
                    $p->execute([$pid]);
                    $prod = $p->fetch();

                    // Insertar ítem
                    $pdo->prepare('INSERT INTO invoice_items (invoice_id, product_id, quantity, price) VALUES (?,?,?,?)')
                        ->execute([$invoice_id, $pid, $qty, $prod['price']]);

                    // Actualizar stock
                    $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?')
                        ->execute([$qty, $pid]);
                }

                $pdo->commit();

                // Redirigir para generar el PDF de la factura
                header('Location: generar_pdf.php?id=' . $invoice_id);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();

                // Registro de errores
                $logPath = __DIR__ . '/../logs/error.log';
                $logDir = dirname($logPath);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0777, true);
                }
                file_put_contents($logPath, date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Nueva factura</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <nav>
        <div style="display:flex;gap:12px;align-items:center">
            <img src="../assets/img/logo.svg" style="height:36px">
            <strong>Campo Vello - Cajero</strong>
        </div>
        <div>
            <a href="ventas.php" style="color:#fff" class="btn">Volver al panel</a>
        </div>
    </nav>
    <div class="container">
        <h2>Nueva factura</h2>

        <?php if ($error) echo '<div class="alert">' . htmlspecialchars($error) . '</div>'; ?>

        <form method="post">
<?= csrf_field() ?>

<div style="display:flex; gap:20px;">

    

    <!-- ================= LISTA DE PRODUCTOS (IZQUIERDA) =================== -->
    <div style="width:60%;">
        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
            <div>
                <label>Cliente</label>
                <select name="client_id" required style="width:220px;">
                    <option value="">Seleccione un cliente</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>

            <input type="text" id="buscador" placeholder="Buscar producto..." style="width:250px;">
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Cant</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>"
                    data-price="<?= $p['price'] ?>">
                    
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td>$<?= number_format($p['price'],2) ?></td>
                    <td><?= $p['stock'] ?></td>

                    <td>
                        <input type="number" min="1" max="<?= $p['stock'] ?>" value="0"
                            style="width:55px;">
                    </td>

                    <td>
                        <button type="button" class="btn agregarBtn">Agregar</button>
                    </td>

                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- ====================== CARRITO (DERECHA) ======================= -->
    <div 
    style="width:40%; background:#f8fff4; padding:15px; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.1);">

        <h3>Carrito</h3>

        <table class="table" id="carritoTabla">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cant</th>
                    <th>Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="carritoBody">
                <tr><td colspan="4" style="text-align:center;color:#888;">No hay productos</td></tr>
            </tbody>
        </table>

        <hr>

        <p>Subtotal: $ <span id="subtotalPagar">0.00</span></p>
        <p>IVA (13%): $ <span id="ivaMonto">0.00</span></p>

        <h3>Total: $ <span id="totalPagar">0.00</span></h3>

        <button class="btn" style="margin-top:10px; width:100%;">Procesar compra</button>
    </div>

</div>

</form>

</div>
    
<script src="../includes/carrito.js"></script>
<script src="../includes/buscador.js"></script>

</body>

</html>