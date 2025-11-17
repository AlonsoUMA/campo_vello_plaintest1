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
            $stmt = $pdo->prepare('INSERT INTO productos (name, category_id, location, price, stock) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $_POST['name'],
                $_POST['category_id'] !== '' ? $_POST['category_id'] : null, // Usar null si está vacío
                $_POST['location'],
                $_POST['price'],
                $_POST['stock']
            ]);
            header('Location: gestionar_productos.php');
            exit;
        }

        // Acción: Eliminar Producto
        if ($action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM productos WHERE id=?');
            $stmt->execute([$_POST['id']]);
            header('Location: gestionar_productos.php');
            exit;
        }

        // Acción: Actualizar Producto (Editar)
        if ($action === 'update') {
            $stmt = $pdo->prepare('UPDATE productos SET name = ?, category_id = ?, location = ?, price = ?, stock = ? WHERE id = ?');
            $stmt->execute([
                $_POST['name'],
                $_POST['category_id'] !== '' ? $_POST['category_id'] : null,
                $_POST['location'],
                $_POST['price'],
                $_POST['stock'],
                $_POST['id']
            ]);
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

    <!-- CSS mínimo para modal (puedes moverlo a style.css) -->
    <style>
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 9999;
            padding: 20px;
        }
        .modal.show {
            display: flex;
        }
        .modal .modal-content {
            background: #fff;
            border-radius: 8px;
            padding: 16px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
        }
        .modal .close {
            position: absolute;
            right: 8px;
            top: 8px;
            background: transparent;
            border: none;
            font-size: 20px;
            cursor: pointer;
        }
        .form-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .form-row input, .form-row select { padding:6px 8px; }
    </style>
</head>
<body>

<nav>
    <div style='display:flex;gap:12px;align-items:center'>
        <img src='../assets/img/logo.svg' style='height:36px' alt='logo'>
        <strong>Campo Vello - Admin</strong>
    </div>
    <div>
        <a href='dashboard.php' style='color:#fff' class="btn">Volver al panel</a>
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
                <option value='<?=htmlspecialchars($c['id'])?>'><?=htmlspecialchars($c['name'])?></option>
            <?php endforeach; ?>
        </select>
        
        <input name='location' placeholder='Ubicación'>
        <input name='price' type='number' step='0.01' value='' required placeholder='Precio'>
        <input name='stock' type='number' value='' required placeholder='Stock'>
        
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
                <td><?=htmlspecialchars($p['id'])?></td>
                <td><?=htmlspecialchars($p['name'])?></td>
                <td><?=htmlspecialchars($p['category'])?></td>
                <td><?=htmlspecialchars($p['location'])?></td>
                <td>$<?=number_format($p['price'], 2)?></td>
                <td><?=htmlspecialchars($p['stock'])?></td>
                <td>
                    <button class='btn' type="button" onclick="openModal('edit-<?=htmlspecialchars($p['id'])?>')">Editar</button> 
                    
                    <form method='post' style='display:inline-block' onsubmit="return confirm('¿Eliminar este producto?');">
                        <?php echo csrf_field(); ?>
                        <input type='hidden' name='action' value='delete'>
                        <input type='hidden' name='id' value='<?=htmlspecialchars($p['id'])?>'>
                        <button class='btn' style='background:#c62828'>Eliminar</button>
                    </form>
                </td>
            </tr>

            <!-- Modal de edición por producto -->
            <div class="modal" id="edit-<?=htmlspecialchars($p['id'])?>" aria-hidden="true" role="dialog" aria-labelledby="edit-label-<?=htmlspecialchars($p['id'])?>">
                <div class="modal-content">
                    <button class="close" type="button" onclick="closeModal('edit-<?=htmlspecialchars($p['id'])?>')" aria-label="Cerrar">&times;</button>
                    <h3 id="edit-label-<?=htmlspecialchars($p['id'])?>">Editar producto #<?=htmlspecialchars($p['id'])?></h3>
                    <form method="post" class="form-row">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?=htmlspecialchars($p['id'])?>">

                        <label style="flex:1 1 100%">
                            Nombre<br>
                            <input name="name" required value="<?=htmlspecialchars($p['name'])?>">
                        </label>

                        <label>
                            Categoría<br>
                            <select name="category_id">
                                <option value=''>Sin categoría</option>
                                <?php foreach ($categories as $c) : ?>
                                    <option value="<?=htmlspecialchars($c['id'])?>" <?=($p['category_id'] == $c['id']) ? 'selected' : ''?>><?=htmlspecialchars($c['name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Ubicación<br>
                            <input name="location" value="<?=htmlspecialchars($p['location'])?>">
                        </label>

                        <label>
                            Precio<br>
                            <input name="price" type="number" step="0.01" required value="<?=htmlspecialchars($p['price'])?>">
                        </label>

                        <label>
                            Stock<br>
                            <input name="stock" type="number" required value="<?=htmlspecialchars($p['stock'])?>">
                        </label>

                        <div style="flex:1 1 100%; display:flex; gap:8px; margin-top:8px;">
                            <button class="btn" type="submit">Guardar cambios</button>
                            <button class="btn" type="button" onclick="closeModal('edit-<?=htmlspecialchars($p['id'])?>')">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    function openModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('show');
            el.setAttribute('aria-hidden', 'false');
        }
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('show');
            el.setAttribute('aria-hidden', 'true');
        }
    }

    // Cerrar modales al hacer click fuera del contenido
    window.addEventListener('click', function(e) { 
        // Si el click es en el overlay (.modal) cerramos
        if (e.target.classList && e.target.classList.contains('modal')) {
            e.target.classList.remove('show'); 
            e.target.setAttribute('aria-hidden','true');
        }
    });

    // Cerrar modal con ESC
    window.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(function(m){
                m.classList.remove('show');
                m.setAttribute('aria-hidden','true');
            });
        }
    });
</script>
</body>
</html>
