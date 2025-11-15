<?php

/**
 * 1. Lógica y Seguridad
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../includes/funciones.php';

// Redirigir si el usuario no es administrador
if (!is_admin()) {
    header('Location: ../index.php');
    exit;
}

$pdo = getPDO();
$error = '';

// Procesamiento de formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verificación CSRF
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $error = 'CSRF inválido';
    } else {
        $action = $_POST['action'] ?? '';

        // Acción: Agregar Producto
        if ($action === 'add') {
            $pdo->prepare('INSERT INTO productos (name, category_id, location, price, stock) VALUES (?, ?, ?, ?, ?)')
                ->execute([
                    $_POST['name'],
                    $_POST['category_id'] ?: null, // Usar null si está vacío
                    $_POST['location'],
                    $_POST['price'],
                    $_POST['stock']
                ]);
            header('Location: gestionar_productos.php');
            exit;
        }

        // Acción: Eliminar Producto
        if ($action === 'delete') {
            $pdo->prepare('DELETE FROM productos WHERE id=?')->execute([$_POST['id']]);
            header('Location: gestionar_productos.php');
            exit;
        }
    }
}

// Obtener datos para la vista
$products = $pdo->query('
    SELECT p.*, c.name as category 
    FROM productos p 
    LEFT JOIN categorias c ON p.category_id = c.id 
    ORDER BY p.id DESC
')->fetchAll();

$categories = $pdo->query('SELECT * FROM categorias')->fetchAll();

?>

<!doctype html>
<html lang="es">
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width,initial-scale=1'>
    <title>Gestionar Productos</title>
    <link rel='stylesheet' href='../assets/css/style.css'>
</head>
<body>

<nav>
    <div style='display:flex;gap:12px;align-items:center'>
        <img src='../assets/img/logo.svg' style='height:36px'>
        <strong>Campo Vello - Admin</strong>
    </div>
    <div>
        <a href='dashboard.php' style='color:#fff'>Volver</a>
    </div>
</nav>

<div class='container'>
    <h2>Productos</h2>

    <?php if (!empty($error)) echo '<div class="alert">'.htmlspecialchars($error).'</div>'; ?>

    <form method='post' class='form-row'>
        <?php echo csrf_field(); ?>
        <input type='hidden' name='action' value='add'>
        
        <input name='name' placeholder='Nombre' required>
        
        <select name='category_id'>
            <option value=''>Sin categoría</option>
            <?php foreach ($categories as $c) : ?>
                <option value='<?=$c['id']?>'><?=htmlspecialchars($c['name'])?></option>
            <?php endforeach; ?>
        </select>
        
        <input name='location' placeholder='Ubicación'>
        <input name='price' type='number' step='0.01' value='0.00' required>
        <input name='stock' type='number' value='0' required>
        
        <button class='btn'>Agregar</button>
    </form>

    <table class='table'>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Categoria</th>
                <th>Ubicación</th>
                <th>Precio</th>
                <th>Stock</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p) : ?>
            <tr>
                <td><?=$p['id']?></td>
                <td><?=htmlspecialchars($p['name'])?></td>
                <td><?=htmlspecialchars($p['category'])?></td>
                <td><?=htmlspecialchars($p['location'])?></td>
                <td><?=number_format($p['price'], 2)?></td>
                <td><?=$p['stock']?></td>
                <td>
                    <button class='btn' onclick="document.getElementById('edit-<?=$p['id']?>').classList.add('show')">Editar</button> 
                    
                    <form method='post' style='display:inline-block'>
                        <?php echo csrf_field(); ?>
                        <input type='hidden' name='action' value='delete'>
                        <input type='hidden' name='id' value='<?=$p['id']?>'>
                        <button class='btn' style='background:#c62828'>Eliminar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    // Lógica para cerrar modales al hacer click fuera
    window.addEventListener('click', function(e) { 
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('show'); 
        }
    });
</script>
</body>
</html>