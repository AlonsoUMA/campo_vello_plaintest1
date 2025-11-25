<?php
/**
 * 1. Lógica y Seguridad
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/autenticacion.php';
// Aseguramos que las funciones de hashing estén disponibles
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

        // Acción: Agregar Usuario
        if ($action === 'add') {
            $password = $_POST['password'] ?? '';
            
            // Validación simple de la contraseña
            if (empty($password)) {
                $error = 'La contraseña no puede estar vacía.';
            } else {
                // --- CAMBIO CLAVE: HASHEAR LA CONTRASEÑA ---
                $hashed_password = hash_password($password);

                try {
                    $pdo->prepare('INSERT INTO usuarios (nombre, email, password, role) VALUES (?, ?, ?, ?)')
                        ->execute([
                            $_POST['nombre'],
                            $_POST['email'],
                            $hashed_password, // Se guarda el hash
                            $_POST['role']
                        ]);
                    header('Location: gestionar_usuarios.php');
                    exit;
                } catch (\PDOException $e) {
                    if ($e->getCode() == '23000') {
                        $error = 'El email o nombre de usuario ya existe.';
                    } else {
                        $error = 'Error al agregar usuario: ' . $e->getMessage();
                    }
                }
            }
        }

        // Acción: Eliminar Usuario
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $pdo->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
                    header('Location: gestionar_usuarios.php');
                    exit;
                } catch (\PDOException $e) {
                    $error = 'Error al eliminar. Verifique que el usuario no tenga facturas asociadas.';
                }
            }
        }
        
        // =========================================================
        // === ACCIÓN NUEVA: ACTUALIZAR/EDITAR USUARIO ===
        // =========================================================
        if ($action === 'update') {
            $user_id = (int)($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? '';
            $password_new = $_POST['password_new'] ?? ''; // Nuevo campo de contraseña
            
            // 1. Validación básica
            if (empty($user_id) || empty($nombre) || empty($email) || !in_array($role, ['admin', 'cajero'])) {
                $error = 'Faltan campos obligatorios o el ID de usuario es inválido.';
            } else {
                
                $params = [];
                $sql = "";

                if (!empty($password_new)) {
                    // Si se proporciona una nueva contraseña, la hasheamos y actualizamos el campo 'password'
                    $hashed_password = hash_password($password_new); 
                    $sql = "UPDATE usuarios SET nombre = ?, email = ?, role = ?, password = ? WHERE id = ?";
                    $params = [$nombre, $email, $role, $hashed_password, $user_id];
                } else {
                    // Si NO se proporciona una nueva contraseña, la consulta es más simple
                    $sql = "UPDATE usuarios SET nombre = ?, email = ?, role = ? WHERE id = ?";
                    $params = [$nombre, $email, $role, $user_id];
                }
                
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    header('Location: gestionar_usuarios.php');
                    exit;

                } catch (\PDOException $e) {
                    // Manejo de error (ej: email duplicado)
                    if ($e->getCode() == '23000') {
                        $error = 'Error: El email ' . htmlspecialchars($email) . ' ya está en uso por otro usuario.';
                    } else {
                        $error = 'Error de base de datos al actualizar el usuario: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}


// Obtener datos para la vista
$users = $pdo->query('SELECT id, nombre, email, role FROM usuarios ORDER BY id DESC')->fetchAll();

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestionar Usuarios</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <nav>
        <div style="display:flex;gap:12px;align-items:center">
            <img src="../assets/img/logo.svg" style="height:36px" alt="logo">
            <strong>Campo Vello - Admin</strong>
        </div>
        <div>
            <a href="dashboard.php" class="btn" style="color:#fff">Volver al panel</a>
        </div>
    </nav>

    <div class="container">
        <h2>Usuarios</h2>
        
        <?php if (!empty($error)) echo '<div class="alert">'.htmlspecialchars($error).'</div>'; ?>

        <form method="post" style="display:flex;gap:8px;margin-bottom:12px">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add">
            
            <input 
                name="nombre" 
                placeholder="Nombre" 
                required 
                style="flex-grow: 1; min-width: 150px;" 
                pattern="^[\p{L}\s]{3,50}$" 
                title="Solo letras (sin números ni caracteres especiales) y espacios. Mínimo 3, máximo 50 caracteres."
                maxlength="50"
                onkeydown="return filterName(event)"
            >
            <input name="email" placeholder="Email" required type="email" pattern="^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,15}$" title="Debe tener formato de correo electrónico (ej: nombre@dominio.com)" style="flex-grow: 1; min-width: 150px;">
            <input name="password" placeholder="Contraseña" required type="password" pattern="(?=.*[A-Za-zñÑ])(?=.*\d)[A-Za-zñÑ\d]{8,15}" title="Debe tener entre 8 y 15 caracteres, incluyendo al menos una letra mayuscula, una minuscula, sin tildes y un número." style="flex-grow: 1; min-width: 150px;">
            
            <select name="role">
                <option value="cajero">Cajero</option>
                <option value="admin">Administrador</option>
            </select>
            
            <button class="btn">Agregar</button>
        </form>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u) : ?>
                <tr>
                    <td><?=$u['id']?></td>
                    <td><?=htmlspecialchars($u['nombre'])?></td>
                    <td><?=htmlspecialchars($u['email'])?></td>
                    <td><?=$u['role']?></td>
                    <td>
                        <button 
                            class="btn" 
                            type="button" 
                            onclick="openEditModal(
                                <?= $u['id'] ?>, 
                                '<?= htmlspecialchars($u['nombre']) ?>', 
                                '<?= htmlspecialchars($u['email']) ?>', 
                                '<?= htmlspecialchars($u['role']) ?>'
                            )"
                            style="background:#5cb85c; margin-right: 5px;"
                        >
                            Editar
                        </button>
                        <form method="post" style="display:inline-block" onsubmit="return confirm('¿Eliminar al usuario <?= htmlspecialchars($u['nombre']) ?>?');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?=$u['id']?>">
                            <button class="btn" style="background:#c62828">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="editUserModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Editar Usuario</h3>
                <button type="button" class="close" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="gestionar_usuarios.php" id="editUserForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit-id"> <label for="edit-nombre">Nombre</label>
                    <input 
                        type="text" 
                        name="nombre" 
                        id="edit-nombre" 
                        required 
                        maxlength="50"
                        pattern="^[\p{L}\s]{3,50}$" 
                        title="Solo letras (sin números ni caracteres especiales) y espacios. Mínimo 3, máximo 50 caracteres."
                        onkeydown="return filterName(event)"
                    >

                    <label for="edit-email">Email</label>
                    <input type="email" name="email" id="edit-email" required>

                    <label for="edit-password-new">Nueva Contraseña (Dejar vacío para no cambiar)</label>
                    <input type="password" name="password_new" id="edit-password-new">

                    <label for="edit-role">Rol</label>
                    <select name="role" id="edit-role">
                        <option value="cajero">Cajero</option>
                        <option value="admin">Administrador</option>
                    </select>
                    
                    <button type="submit" class="btn" style="margin-top:15px; background:#5cb85c;">Guardar Cambios</button>
                </form>
            </div>
        </div>
    </div>

   

    <script>
        // Funciones básicas para manejo de modales
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

        // FUNCIÓN: ABRIR MODAL DE EDICIÓN DE USUARIO
        function openEditModal(id, nombre, email, role) {
            // 1. Rellenar los campos del formulario
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-nombre').value = nombre;
            document.getElementById('edit-email').value = email;
            
            // 2. Seleccionar el rol correcto en el <select>
            document.getElementById('edit-role').value = role;

            // 3. Abrir el modal
            openModal('editUserModal');
            
            // 4. Limpiar el campo de contraseña cada vez que se abre
            document.getElementById('edit-password-new').value = '';
        }

        window.openEditModal = openEditModal; 
        
        /**
         * FUNCIÓN NUEVA: JavaScript para filtrar las pulsaciones de teclado en el campo de nombre.
         * Permite: letras (a-z, A-Z), letras acentuadas (Á, É, etc.), 'ñ', y espacios.
         * Bloquea números y caracteres especiales.
         */
        function filterName(event) {
            const key = event.key;
            
            // 1. Permite teclas de control esenciales (Retroceso, Tab, Flechas, etc.)
            if (event.ctrlKey || event.altKey || event.metaKey || 
                key === 'Tab' || key === 'Backspace' || key === 'Delete' || 
                key.startsWith('Arrow') || key === 'Home' || key === 'End') {
                return true; 
            }
            
            // 2. Patrón de caracteres permitidos (letras y espacios)
            // [\p{L}\s] coincide con cualquier letra Unicode y espacio en blanco.
            const allowedPattern = /^[\p{L}\s]$/u; // La 'u' es importante para el soporte Unicode
            
            // 3. Bloquea si la tecla presionada no coincide con el patrón de permitidos.
            if (key.length === 1 && !allowedPattern.test(key)) {
                event.preventDefault(); // Detiene la acción de la tecla
                return false;
            }
            
            return true;
        }

        
    </script>

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