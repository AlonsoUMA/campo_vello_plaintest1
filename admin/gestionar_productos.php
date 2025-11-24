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

// Procesamiento de formulario POST (Agregar, Eliminar, Actualizar)
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
                $_POST['category_id'] !== '' ? $_POST['category_id'] : null, 
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
            // MODIFICACIÓN IMPORTANTE: Se debe asegurar que el producto no esté referenciado
            // por ninguna factura antes de eliminar, o usar ON DELETE CASCADE en la BD. 
            // Para mantener la consistencia, asumimos que la BD no tiene restricción.
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


// ------------------------------------------------------------------
// LÓGICA DE BÚSQUEDA Y PAGINACIÓN PARA PRODUCTOS
// ------------------------------------------------------------------

// Capturar término de búsqueda
$search_query = $_GET['search'] ?? ''; 
$is_searching = !empty($search_query);
$search_param = '%' . $search_query . '%'; // Parámetro para LIKE

// Variables de Paginación (solo se usan si NO se está buscando)
$limit = 10; // Límite de productos por página
$page = (int) ($_GET['page'] ?? 1); // Página actual, por defecto 1

// --- 1. Contar el total de productos (con o sin filtro de búsqueda) ---
$count_sql = 'SELECT COUNT(id) FROM productos';
$main_sql = '
    SELECT p.*, c.name as category 
    FROM productos p 
    LEFT JOIN categorias c ON p.category_id = c.id';
$params = [];

// AÑADIR FILTRO DE BÚSQUEDA si existe
if ($is_searching) {
    // Aplicamos el filtro en la tabla productos por el nombre
    $count_sql .= ' WHERE name LIKE :search';
    $main_sql .= ' WHERE p.name LIKE :search';
    $params[':search'] = $search_param;
}

$total_products_stmt = $pdo->prepare($count_sql);

// BIND el parámetro de búsqueda si se está usando
if ($is_searching) {
    $total_products_stmt->bindValue(':search', $search_param, PDO::PARAM_STR);
}
$total_products_stmt->execute();
$total_products = $total_products_stmt->fetchColumn();

// --- Lógica de Paginación (SÓLO si NO hay búsqueda) ---
if ($is_searching) {
    // Si se está buscando, no hay paginación:
    $total_pages = 1;
    $offset = 0;
    $limit_clause = ''; // No LIMIT
} else {
    // Si NO se está buscando, aplicamos la paginación:
    $total_pages = ceil($total_products / $limit);

    // Ajustar la página si es inválida
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_products > 0) $page = $total_pages;

    // Calcular el punto de inicio (offset)
    $offset = ($page - 1) * $limit;
    
    // Agregar LIMIT y OFFSET a la consulta principal
    $limit_clause = ' LIMIT :limit OFFSET :offset';
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;
}

// ------------------------------------------------------------------
// --- 2. OBTENER PRODUCTOS PARA LA VISTA ACTUAL ---
// ------------------------------------------------------------------
$sql = $main_sql . ' ORDER BY p.id DESC' . $limit_clause;

// Ejecutar la consulta preparada
$stmt = $pdo->prepare($sql);

// BIND todos los parámetros
foreach ($params as $key => $value) {
    // Determinamos el tipo de parámetro
    $type = PDO::PARAM_STR;
    if ($key === ':limit' || $key === ':offset') {
        $type = PDO::PARAM_INT;
    }
    $stmt->bindValue($key, $value, $type);
}

$stmt->execute();
$products = $stmt->fetchAll();

// Obtener categorías (esta consulta no cambia)
$categories = $pdo->query('SELECT * FROM categorias')->fetchAll();
?>

<!doctype html>
<html lang="es">

<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width,initial-scale=1'>
    <title>Gestionar Productos</title>
    <link rel='stylesheet' href='../assets/css/style.css'>

    <style>
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
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

        .form-row {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .form-row input,
        .form-row select {
            padding: 6px 8px;
        }
        
        /* ESTILOS DE AGRUPACIÓN */
        .action-buttons-group {
            display: flex;  /* Alinea los elementos horizontalmente */
            gap: 8px;       /* Espacio entre ellos */
            margin-bottom: 20px; /* Separación debajo del grupo */
            align-items: center; /* Centra verticalmente todos los elementos */
        }

        /* ESTILO CLAVE: Asegura que los botones Agregar/Imprimir tengan el mismo ancho */
        .btn-equal-size {
            width: 150px; /* Define un ancho fijo deseado para la igualdad */
            text-align: center; /* Centra el texto dentro del botón */
        }
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
        <div class='container'>
            <h2>Productos</h2>

            <?php if (!empty($error)) echo '<div class="alert">' . htmlspecialchars($error) . '</div>'; ?>

            <form method='post' id="add-product-form">
                <?php echo csrf_field(); ?>
                <input type='hidden' name='action' value='add'>

                <div class="form-row">
                    <input name='name' placeholder='Nombre' pattern="[a-zA-Z0-9ñÑáÁéÉíÍóÓúÚ\s\-,()]+" required>
                    <select name='category_id' required>
                        <option value=''>Sin categoría</option>
                        <?php foreach ($categories as $c) : ?>
                            <option value='<?= htmlspecialchars($c['id']) ?>'><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name='location' pattern="[a-zA-Z0-9ñÑáÁéÉíÍóÓúÚ\s\-,()]+" required placeholder='Ubicación'>
                    <input name='price' type='number' step='0.01' value='' required placeholder='Precio'>
                    <input name='stock' type='number' value='' required placeholder='Stock'>
                </div>
            </form> </br>

            <div class="action-buttons-group">
                <button class='btn btn-equal-size' form="add-product-form">Agregar</button>
                
                <a href="reporte_inventario_pdf.php" target="_blank" class="btn btn-equal-size" style="background-color:#337ab7;">
                    Imprimir inventario
                </a>

                <!--Campo de búsqueda -->
                <div style="margin-left: auto; display: flex; align-items: center; gap: 8px;">
                    <input type="text" id="search_input" placeholder="Buscar producto..."
                        pattern="a-zA-Z0-9ñÑáÁéÉíÍóÓúÚ\s]+"
                        value="<?= htmlspecialchars($search_query) ?>" style="width:200px;">
                    <button type="button" class="btn" id="search_button">Buscar</button>
                    <?php if ($is_searching): ?>
                        <a href="gestionar_productos.php" class="btn" style="background-color:#ccc; color:#333;">X</a>
                        <span style="font-size: 0.9em; color: #059669; white-space: nowrap;">(<?= $total_products ?> resultados)</span>
                    <?php endif; ?>
                </div>
                            

            </div>
            
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
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">
                                <?php if ($is_searching): ?>
                                    No se encontraron productos con el término "<?= htmlspecialchars($search_query) ?>"
                                <?php else: ?>
                                    No hay productos para mostrar.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <?php foreach ($products as $p) : ?>
                        <tr>
                            <td><?= htmlspecialchars($p['id']) ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['category']) ?></td>
                            <td><?= htmlspecialchars($p['location']) ?></td>
                            <td>$<?= number_format($p['price'], 2) ?></td>
                            <td><?= htmlspecialchars($p['stock']) ?></td>
                            <td>
                                <button class='btn' type="button" onclick="openModal('edit-<?= htmlspecialchars($p['id']) ?>')">Editar</button>

                                <form method='post' style='display:inline-block' onsubmit="return confirm('¿Eliminar este producto?');">
                                    <?php echo csrf_field(); ?>
                                    <input type='hidden' name='action' value='delete'>
                                    <input type='hidden' name='id' value='<?= htmlspecialchars($p['id']) ?>'>
                                    <button class='btn' style='background:#c62828'>Eliminar</button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal" id="edit-<?= htmlspecialchars($p['id']) ?>" aria-hidden="true" role="dialog" aria-labelledby="edit-label-<?= htmlspecialchars($p['id']) ?>">
                            <div class="modal-content">
                                <button class="close" type="button" onclick="closeModal('edit-<?= htmlspecialchars($p['id']) ?>')" aria-label="Cerrar">&times;</button>
                                <h3 id="edit-label-<?= htmlspecialchars($p['id']) ?>">Editar producto #<?= htmlspecialchars($p['id']) ?></h3>
                                <form method="post" class="form-row">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">

                                    <label style="flex:1 1 100%">
                                        Nombre<br>
                                        <input name="name" pattern="[a-zA-Z0-9ñÑáÁéÉíÍóÓúÚ\s\-,()]+" required value="<?= htmlspecialchars($p['name']) ?>">
                                    </label>

                                    <label>
                                        Categoría<br>
                                        <select name="category_id">
                                            <option value='' required>Sin categoría</option>
                                            <?php foreach ($categories as $c) : ?>
                                                <option value="<?= htmlspecialchars($c['id']) ?>" <?= ($p['category_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>

                                    <label>
                                        Ubicación<br>
                                        <input name="location" pattern="[a-zA-Z0-9ñÑáÁéÉíÍóÓúÚ\s\-,()]+" value="<?= htmlspecialchars($p['location']) ?>">
                                    </label>

                                    <label>
                                        Precio<br>
                                        <input name="price" type="number" step="0.01" required value="<?= htmlspecialchars($p['price']) ?>">
                                    </label>

                                    <label>
                                        Stock<br>
                                        <input name="stock" type="number" required value="<?= htmlspecialchars($p['stock']) ?>">
                                    </label>

                                    <div style="flex:1 1 100%; display:flex; gap:8px; margin-top:8px;">
                                        <button class="btn" type="submit">Guardar cambios</button>
                                        <button class="btn" type="button" onclick="closeModal('edit-<?= htmlspecialchars($p['id']) ?>')">Cancelar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Lógica de paginación solo si NO se está buscando -->
            <div style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;">
                <?php if (!$is_searching && $total_pages > 1) : ?>
                    <?php 
                        // Genera la URL base
                        $base_url = 'gestionar_productos.php?page=';
                    ?>

                    <?php if ($page > 1) : ?>
                        <a href="<?= $base_url . ($page - 1) ?>" class="btn">Anterior</a>
                    <?php else : ?>
                        <button class="btn" disabled>Anterior</button>
                    <?php endif; ?>

                    <?php 
                        // Muestra hasta 5 páginas centradas alrededor de la página actual
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        if ($start > 1) {
                            echo '<a href="' . $base_url . '1" class="btn" style="background-color:#eee; color:#333;">1</a>';
                            if ($start > 2) {
                                echo '<span style="padding: 6px 8px;">...</span>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++) : ?>
                            <a href="<?= $base_url . $i ?>" class="btn" style="<?= $i == $page ? 'background-color:#000; color:#fff;' : 'background-color:#eee; color:#333;' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php 
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<span style="padding: 6px 8px;">...</span>';
                            }
                            echo '<a href="' . $base_url . $total_pages . '" class="btn" style="background-color:#eee; color:#333;">' . $total_pages . '</a>';
                        }
                        ?>

                    <?php if ($page < $total_pages) : ?>
                        <a href="<?= $base_url . ($page + 1) ?>" class="btn">Siguiente</a>
                    <?php else : ?>
                        <button class="btn" disabled>Siguiente</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            </div>

        <script>
            // MODIFICACIÓN: JS para manejar la búsqueda por URL
            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('search_input');
                const searchButton = document.getElementById('search_button');

                if (searchButton) {
                    searchButton.addEventListener('click', function() {
                        const query = searchInput.value.trim();
                        if (query) {
                            // Redirige a la página con el parámetro 'search' en la URL
                            window.location.href = `gestionar_productos.php?search=${encodeURIComponent(query)}`;
                        } else {
                            // Si el campo está vacío, quita el parámetro 'search' volviendo a la URL base
                            window.location.href = 'gestionar_productos.php';
                        }
                    });

                    // Opcional: Permitir buscar presionando Enter
                    searchInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault(); // Evita el envío del formulario si está dentro de uno
                            searchButton.click();
                        }
                    });
                }
                
                // Funciones de modal existentes...
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

                window.openModal = openModal;
                window.closeModal = closeModal;


                // Cerrar modales al hacer click fuera del contenido
                window.addEventListener('click', function(e) {
                    if (e.target.classList && e.target.classList.contains('modal')) {
                        e.target.classList.remove('show');
                        e.target.setAttribute('aria-hidden', 'true');
                    }
                });

                // Cerrar modal con ESC
                window.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        document.querySelectorAll('.modal.show').forEach(function(m) {
                            m.classList.remove('show');
                            m.setAttribute('aria-hidden', 'true');
                        });
                    }
                });
            });
        </script>
</body>

</html>