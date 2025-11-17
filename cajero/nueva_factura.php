<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';

require_login();

$pdo = getPDO();

// Obtener listas para el formulario
$products = $pdo->query('SELECT * FROM productos WHERE stock > 0 ORDER BY name')->fetchAll();

// MODIFICACIÓN: Se usa un ALIAS "name AS nombre" para poder usar la variable $c['nombre'] en el HTML.
$clients = $pdo->query('SELECT id, name AS nombre FROM clientes ORDER BY name')->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $error = 'CSRF inválido';
    } else {
        $items = $_POST['items'] ?? [];
        $client_id = (int)($_POST['client_id'] ?? 0);

        if (empty($items)) {
            $error = 'Debe seleccionar al menos un producto';
        } else {
            try {
                // Iniciar transacción para asegurar la consistencia de los datos (factura e inventario)
                $pdo->beginTransaction();
                $total = 0;

                // 1. Pre-validación y cálculo del total (con bloqueo de fila FOR UPDATE)
                foreach ($items as $pid => $qty) {
                    $qty = (int)$qty;
                    if ($qty <= 0) continue;

                    $p = $pdo->prepare('SELECT * FROM productos WHERE id = ? FOR UPDATE');
                    $p->execute([$pid]);
                    $prod = $p->fetch();

                    if (!$prod) {
                        throw new Exception('Producto no existe: ' . $pid);
                    }
                    if ($prod['stock'] < $qty) {
                        throw new Exception('Stock insuficiente para: ' . $prod['name']);
                    }

                    $total += $prod['price'] * $qty;
                }

                // 2. Insertar la factura principal
                $stmt = $pdo->prepare('INSERT INTO facturas (user_id, client_id, total) VALUES (?,?,?)');
                $stmt->execute([$_SESSION['user']['id'], $client_id, $total]);
                $invoice_id = $pdo->lastInsertId();

                // 3. Insertar los ítems de la factura y actualizar el stock
                foreach ($items as $pid => $qty) {
                    $qty = (int)$qty;
                    if ($qty <= 0) continue;

                    // Re-obtener el precio para evitar problemas de concurrencia y asegurar que sea el precio final
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

                // Crear carpeta si no existe
                $logDir = dirname($logPath);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0777, true);
                }

                // Registrar error
                file_put_contents($logPath, date('Y-m-d H:i:s') . " - $error\n", FILE_APPEND);
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
            <?php echo csrf_field(); ?>

            <label>Cliente</label>
            <select name="client_id" required style="width: 250px;">
                <option value="">Seleccione un cliente</option>
                <?php
                // Ahora usamos $c['nombre'] gracias al alias en la consulta SQL
                foreach ($clients as $c) echo "<option value='{$c['id']}'>" . htmlspecialchars($c['nombre']) . "</option>";
                ?>
            </select>
            <br><br>
            <!--Buscador de productos-->
            <div class="form-floating"><input
                type="text" 
                id="buscador" 
                placeholder="Buscar producto..." 
                style="width:300px;padding:6px;margin-bottom:12px;"
            ></div>


            <table class="table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td>$<?= number_format($p['price'], 2) ?></td>
                            <td><?= $p['stock'] ?></td>
                            <td>
                                <input type="number"
                                    name="items[<?= $p['id'] ?>]"
                                    min="0"
                                    max="<?= $p['stock'] ?>"
                                    value="0">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 style="text-align:right;margin-top:20px;">
                Total a pagar: $ <span id="totalPagar">0.00</span>
            </h3>
            <div style="text-align:right;margin-top:12px;">
                <button class="btn">Generar factura</button>
            </div>
        </form>
    </div>
<script>// Script para calcular el total en tiempo real
    function calcularTotal() {
        let total = 0;

        // Recorrer todas las filas de productos
        document.querySelectorAll("table tbody tr").forEach(fila => {
            let precio = parseFloat(fila.querySelector("td:nth-child(2)").textContent.replace('$',''));
            let qty    = parseInt(fila.querySelector("input[type='number']").value) || 0;

            total += precio * qty;
        });

        document.getElementById("totalPagar").textContent = total.toFixed(2);
    }

    // Activar cálculo cuando se cambien cantidades
    document.querySelectorAll("input[type='number']").forEach(input => {
        input.addEventListener("input", calcularTotal);
    });
</script>

</body>

<script> // Scrip de búsqueda en la tabla de productos
document.getElementById('buscador').addEventListener('input', function () {
    let filtro = this.value.toLowerCase();
    let filas = document.querySelectorAll("table tbody tr");

    filas.forEach(fila => {
        let nombre = fila.querySelector("td:first-child").textContent.toLowerCase();
        fila.style.display = nombre.includes(filtro) ? "" : "none";
    });
});
</script>

</html>